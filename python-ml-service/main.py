from fastapi import FastAPI, HTTPException
import redis
import json

app = FastAPI(
    title="Flood Warning and Monitoring",
    description="Service pendeteksi banjir berdasarkan cuaca, dan tinggi air.",
    version="1.0.0"
)

try:
    redis_client = redis.Redis(host='localhost', port=6700, decode_responses=True)
    redis_client.ping()
except Exception:
    redis_client = None

@app.get("/health")
async def health_check():
    return {
        "status": "ok", 
        "service": "ready", 
        "redis_connected": redis_client is not None
    }

@app.get("/predict/realtime/banjir")
async def get_realtime():
    try:
        if not redis_client:
            raise HTTPException(status_code=500, detail="Terjadi kesalahan pada redis server")
        
        data = redis_client.get("status_banjir_terakhir")
        if not data:
            return {"message": "Belum ada data sensor banjir yang diproses oleh RabbitMQ"}
        
        return json.loads(data)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    
@app.get("/predict/realtime/curah-hujan")
async def get_realtime_hujan():
    try:
        if not redis_client:
            raise HTTPException(status_code=500, detail="Terjadi kesalahan pada redis server")
        
        data = redis_client.get("estimasi_hujan_terakhir")
        if not data:
            return {"message": "Belum ada data sensor curah hujan yang diproses oleh RabbitMQ"}
        
        return json.loads(data)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))