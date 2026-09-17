"""
Shared model-evaluation helper used by both the risk-grid model comparison
(main.py's /model-metrics) and the DBSCAN-zone model comparison (zones.py's
/zone-model-metrics) — kept in its own module so neither file has to import
from the other.
"""
import numpy as np
from sklearn.metrics import (
    accuracy_score, precision_score, recall_score, f1_score,
    roc_auc_score, roc_curve, precision_recall_curve, confusion_matrix,
)


def evaluate_model(model, X_train, y_train, w_train, X_test, y_test, feature_names=None) -> dict:
    """Fit one classifier and score it on held-out data. Not every estimator
    (e.g. KNeighborsClassifier) accepts sample_weight, so that's tried first
    and silently dropped if unsupported."""
    try:
        model.fit(X_train, y_train, sample_weight=w_train)
    except TypeError:
        model.fit(X_train, y_train)

    y_pred = model.predict(X_test)
    y_proba = model.predict_proba(X_test)[:, 1]

    metrics = {
        "accuracy": round(float(accuracy_score(y_test, y_pred)), 3),
        "precision": round(float(precision_score(y_test, y_pred, zero_division=0)), 3),
        "recall": round(float(recall_score(y_test, y_pred, zero_division=0)), 3),
        "f1": round(float(f1_score(y_test, y_pred, zero_division=0)), 3),
        "roc_auc": round(float(roc_auc_score(y_test, y_proba)), 3) if len(set(y_test)) > 1 else None,
    }

    cm = confusion_matrix(y_test, y_pred, labels=[0, 1]).tolist()
    metrics["confusion_matrix"] = {
        "tn": int(cm[0][0]), "fp": int(cm[0][1]),
        "fn": int(cm[1][0]), "tp": int(cm[1][1]),
    }

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

    return metrics
