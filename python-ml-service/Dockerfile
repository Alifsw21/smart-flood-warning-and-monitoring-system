FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Spec §5.1 — train models at image build when .pkl are not committed (DVC/gitignored)
RUN python trains/train_model_water_level.py \
    && python trains/train_model_cuaca.py \
    && python trains/train_model_curah_hujan.py \
    && python trains/train_model_anomaly.py

EXPOSE 8000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
