"""
Person + wildlife detection on uploaded footage using pretrained YOLOv8
models — no training involved, pure off-the-shelf inference. This is the
beginning of the planned "Analiza snimke" (footage analysis) feature,
separate from every other approach in this service (all of which work on
GPS/timestamp detection records, never on actual video).

Two separate models, picked by the caller (`thermal=` flag), because a model
trained on ordinary colour photos generalises poorly to thermal/infrared
footage — very different visual appearance (heat signature vs. colour/texture):
  - yolov8n.pt        — COCO weights (person + wildlife/livestock), for
                        ordinary drone/camera video.
  - thermal_model.pt  — pitangent-ds/YOLOv8-human-detection-thermal
                        (https://huggingface.co/pitangent-ds/YOLOv8-human-detection-thermal,
                        AGPL-3.0), fine-tuned specifically on thermal imagery.
                        Single class only ("HUMAN") — no animal classes for
                        thermal footage yet.

The video isn't analysed frame-by-frame (30+ fps would be far too slow on
CPU) — it's *sampled* at `sample_fps` (default 2 frames/second) and only
those sampled frames are run through YOLO. The frontend plays the original
video and overlays boxes for whichever sampled frame is closest to the
current playback time, which reads as "live" detection during playback even
though every frame was actually analysed up front, before playback starts.
"""

import cv2
from ultralytics import YOLO

MODEL_PATH = "yolov8n.pt"  # nano model — fastest, good enough for CPU inference
THERMAL_MODEL_PATH = "thermal_model.pt"

# COCO class ids this app cares about: person, plus the wildlife/livestock
# categories actually plausible on a Croatian border-area drone/camera feed.
# COCO also has elephant/zebra/giraffe (ids 20, 22, 23) — left out, they'd
# never legitimately appear here and would only ever be false positives.
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

# The thermal model only knows one class ("HUMAN", id 0) — translated to the
# same "Osoba" label the regular model uses, so the frontend doesn't need to
# know or care which model actually produced a box.
THERMAL_CLASSES = {0: "Osoba"}

_model: YOLO | None = None
_thermal_model: YOLO | None = None


def _get_model() -> YOLO:
    """Loads the model once per process (loading it is slow — seconds — so
    every request reusing the same instance matters far more here than for
    the scikit-learn models elsewhere, which are cheap to retrain per request)."""
    global _model
    if _model is None:
        _model = YOLO(MODEL_PATH)
    return _model


def _get_thermal_model() -> YOLO:
    global _thermal_model
    if _thermal_model is None:
        _thermal_model = YOLO(THERMAL_MODEL_PATH)
    return _thermal_model


def analyze_video(path: str, sample_fps: float = 2.0, conf_threshold: float = 0.35,
                   max_seconds: float = 60.0, thermal: bool = False) -> dict:
    """
    Runs detection on a sample of frames from the video at `path`, using the
    thermal-specific model when `thermal=True` and the regular COCO model
    otherwise. Returns normalised (0-1) box coordinates so the frontend can
    scale them to whatever size the <video> element is actually displayed
    at, regardless of the source video's real resolution.
    """
    model = _get_thermal_model() if thermal else _get_model()
    class_map = THERMAL_CLASSES if thermal else DETECT_CLASSES
    class_ids = list(class_map.keys())

    cap = cv2.VideoCapture(path)
    if not cap.isOpened():
        return {"error": "Datoteka se ne može očitati kao video."}

    native_fps = cap.get(cv2.CAP_PROP_FPS) or 25.0
    total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
    duration = total_frames / native_fps if native_fps > 0 else 0.0

    # Sample every Nth frame so total analysis time stays roughly
    # (video length in seconds) / sample_fps * (time per YOLO call) — not
    # (video length) * native_fps, which would be 10-15x slower for nothing:
    # a person's position barely changes frame-to-frame at 25-30fps.
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
