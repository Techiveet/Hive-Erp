FROM python:3.12-slim

ARG DEBIAN_FRONTEND=noninteractive

ENV PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PIP_ROOT_USER_ACTION=ignore

# Install FFmpeg (full build with all codecs)
RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install Python deps
RUN pip install --no-cache-dir flask gunicorn

WORKDIR /app
COPY server.py .

EXPOSE 9090

CMD ["gunicorn", "--bind", "0.0.0.0:9090", "--timeout", "600", "--workers", "2", "server:app"]
