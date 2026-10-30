# Zajednička funkcija za evaluaciju modela.
# Koriste je usporedba modela za mrežu rizika (main.py) i za DBSCAN žarišta (zones.py).
import numpy as np
from sklearn.metrics import (
    accuracy_score, precision_score, recall_score, f1_score,
    roc_auc_score, roc_curve, precision_recall_curve, confusion_matrix,
    average_precision_score,
)


# Traži prag odluke s najvećim F1 na validacijskom skupu.
# Ako u validacijskom skupu postoji samo jedan razred, vraća se standardni prag 0,5.
def _best_f1_threshold(y_val, proba_val) -> float:
    if len(set(y_val)) < 2:
        return 0.5
    precision, recall, thresholds = precision_recall_curve(y_val, proba_val)
    if len(thresholds) == 0:
        return 0.5
    f1 = np.where((precision[:-1] + recall[:-1]) > 0,
                   2 * precision[:-1] * recall[:-1] / (precision[:-1] + recall[:-1] + 1e-12), 0.0)
    return float(thresholds[int(np.argmax(f1))])


# Uči jedan model i računa mjere na testnom skupu.
# Ako je zadan validacijski skup, prag odluke bira se na njemu, a testni skup se ne dira.
# ROC-AUC, AP i krivulje ne ovise o pragu.
# Uz return_artifacts=True vraća i procjene na testnom skupu (za pokrivenost top-K žarišta).
def evaluate_model(model, X_train, y_train, w_train, X_test, y_test, feature_names=None,
                    X_val=None, y_val=None, return_artifacts=False):
    # KNN ne podržava težine primjera, pa se tada uči bez njih
    try:
        model.fit(X_train, y_train, sample_weight=w_train)
    except TypeError:
        model.fit(X_train, y_train)

    y_proba = model.predict_proba(X_test)[:, 1]

    threshold = 0.5
    if X_val is not None and y_val is not None and len(y_val) > 0:
        proba_val = model.predict_proba(X_val)[:, 1]
        threshold = _best_f1_threshold(y_val, proba_val)
    y_pred = (y_proba >= threshold).astype(int)

    metrics = {
        "threshold": round(float(threshold), 3),
        "accuracy": round(float(accuracy_score(y_test, y_pred)), 3),
        "precision": round(float(precision_score(y_test, y_pred, zero_division=0)), 3),
        "recall": round(float(recall_score(y_test, y_pred, zero_division=0)), 3),
        "f1": round(float(f1_score(y_test, y_pred, zero_division=0)), 3),
        "roc_auc": round(float(roc_auc_score(y_test, y_proba)), 3) if len(set(y_test)) > 1 else None,
        "average_precision": round(float(average_precision_score(y_test, y_proba)), 3) if len(set(y_test)) > 1 else None,
    }

    cm = confusion_matrix(y_test, y_pred, labels=[0, 1]).tolist()
    metrics["confusion_matrix"] = {
        "tn": int(cm[0][0]), "fp": int(cm[0][1]),
        "fn": int(cm[1][0]), "tp": int(cm[1][1]),
    }

    # ROC i PR krivulje, skraćene na najviše 40 točaka za prikaz na stranici
    if len(set(y_test)) > 1:
        fpr, tpr, _ = roc_curve(y_test, y_proba)
        if len(fpr) > 40:
            idx = np.linspace(0, len(fpr) - 1, 40).astype(int)
            fpr, tpr = fpr[idx], tpr[idx]
        metrics["roc_curve"] = {
            "fpr": [round(float(v), 4) for v in fpr],
            "tpr": [round(float(v), 4) for v in tpr],
        }

        prec, rec, _ = precision_recall_curve(y_test, y_proba)
        if len(prec) > 40:
            idx = np.linspace(0, len(prec) - 1, 40).astype(int)
            prec, rec = prec[idx], rec[idx]
        metrics["pr_curve"] = {
            "precision": [round(float(v), 4) for v in prec],
            "recall": [round(float(v), 4) for v in rec],
        }

    if feature_names and hasattr(model, "feature_importances_"):
        metrics["feature_importances"] = {
            name: round(float(imp), 3)
            for name, imp in zip(feature_names, model.feature_importances_)
        }

    if return_artifacts:
        return metrics, y_proba, model
    return metrics
