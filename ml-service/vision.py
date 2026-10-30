# Prepoznavanje osoba i životinja na snimkama pomoću gotovih YOLOv8 modela (bez učenja).
# Koriste se dva modela: običan COCO model za snimke u boji i poseban model za termalne snimke,
# jer model učen na fotografijama u boji slabo radi na termalnim snimkama.
#  - yolov8n.pt: COCO model (osobe i životinje)
#  - thermal_model.pt: pitangent-ds/YOLOv8-human-detection-thermal (AGPL-3.0), prepoznaje samo osobe
# Video se ne analizira kadar po kadar nego se uzima nekoliko kadrova u sekundi (sample_fps),
# a stranica tijekom reprodukcije prikazuje okvire najbližeg analiziranog kadra.

import cv2
from ultralytics import YOLO

MODEL_PATH = "yolov8n.pt"  # najmanji model, dovoljno brz za rad na procesoru
THERMAL_MODEL_PATH = "thermal_model.pt"

# COCO razredi koji nas zanimaju: osoba i životinje koje se realno mogu pojaviti uz granicu
DETECT_CLASSES = {
    0: "Osoba",
    14: "Ptica",
    15: "Mačka",
    16: "Pas",
    17: "Konj",
    18: "Ovca",
    19: "Krava",
    21: "Medvjed",
}

# Termalni model zna samo razred "HUMAN", koji se prikazuje kao "Osoba"
THERMAL_CLASSES = {0: "Osoba"}

_model: YOLO | None = None
_thermal_model: YOLO | None = None


# Model se učitava samo jednom jer učitavanje traje nekoliko sekundi
def _get_model() -> YOLO:
    global _model
    if _model is None:
        _model = YOLO(MODEL_PATH)
    return _model


def _get_thermal_model() -> YOLO:
    global _thermal_model
    if _thermal_model is None:
        _thermal_model = YOLO(THERMAL_MODEL_PATH)
    return _thermal_model


# Analizira uzorak kadrova iz videa i vraća okvire s koordinatama od 0 do 1,
# kako bi ih stranica mogla prikazati bez obzira na veličinu videa
def analyze_video(path: str, sample_fps: float = 2.0, conf_threshold: float = 0.35,
                   max_seconds: float = 60.0, thermal: bool = False) -> dict:
    model = _get_thermal_model() if thermal else _get_model()
    class_map = THERMAL_CLASSES if thermal else DETECT_CLASSES
    class_ids = list(class_map.keys())

    cap = cv2.VideoCapture(path)
    if not cap.isOpened():
        return {"error": "Datoteka se ne može očitati kao video."}

    native_fps = cap.get(cv2.CAP_PROP_FPS) or 25.0
    total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
    duration = total_frames / native_fps if native_fps > 0 else 0.0

    # Analizira se svaki N-ti kadar, jer se položaj osobe između susjednih kadrova gotovo ne mijenja
    frame_step = max(1, round(native_fps / sample_fps))
    max_frames = int(max_seconds * native_fps) if max_seconds else total_frames

    frames_out = []
    frame_idx = 0
    while True:
        ok, frame = cap.read()
        if not ok or frame_idx >= max_frames:
            break

        if frame_idx % frame_step == 0:
            results = model.predict(frame, classes=class_ids, conf=conf_threshold, verbose=False)
            boxes = []
            for r in results:
                xyxyn = r.boxes.xyxyn.tolist()
                confs = r.boxes.conf.tolist()
                classes = r.boxes.cls.tolist()
                for (x1, y1, x2, y2), conf, cls in zip(xyxyn, confs, classes):
                    boxes.append({
                        "x1": round(x1, 4), "y1": round(y1, 4),
                        "x2": round(x2, 4), "y2": round(y2, 4),
                        "confidence": round(conf * 100, 1),
                        "label": class_map.get(int(cls), "Nepoznato"),
                    })
            frames_out.append({"time": round(frame_idx / native_fps, 2), "boxes": boxes})

        frame_idx += 1

    cap.release()

    return {
        "fps": round(native_fps, 2),
        "duration": round(min(duration, max_seconds) if max_seconds else duration, 2),
        "sample_fps": sample_fps,
        "frame_count": len(frames_out),
        "thermal": thermal,
        "frames": frames_out,
    }
