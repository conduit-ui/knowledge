FROM python:3.11-slim

WORKDIR /app

# Install dependencies first (cached layer)
RUN pip install --no-cache-dir \
    sentence-transformers \
    flask \
    gunicorn

# Copy server code
COPY server.py .

# Pre-download the model during build
RUN python -c "from sentence_transformers import SentenceTransformer; import os; SentenceTransformer(os.environ.get('EMBEDDING_MODEL', 'all-MiniLM-L6-v2'))"

EXPOSE 8001

CMD ["gunicorn", "-w", "2", "-b", "0.0.0.0:8001", "server:app"]
