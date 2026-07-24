@echo off
echo Starting DroneManager ML Service on port 8001...
cd /d "%~dp0"
C:\laragon\bin\python\python-3.13\python.exe -m uvicorn main:app --host 127.0.0.1 --port 8001 --reload
pause
