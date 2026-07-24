FROM python:3.11-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    HOST=0.0.0.0 \
    PORT=8765

WORKDIR /app

# No system-level dependencies needed (OpenCV removed)

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy ALL Python source files — not a hardcoded subset
COPY *.py ./

EXPOSE 8765

HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8765/health', timeout=3)"

CMD ["python", "server.py"]