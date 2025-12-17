"""
Embedding server for conduit-ui/knowledge using sentence-transformers.
Provides a REST API for generating text embeddings.
"""

import os
from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer

app = Flask(__name__)

# Load model on startup (cached in volume)
MODEL_NAME = os.environ.get('EMBEDDING_MODEL', 'all-MiniLM-L6-v2')
model = None


def get_model():
    """Lazy load the model."""
    global model
    if model is None:
        print(f"Loading model: {MODEL_NAME}")
        model = SentenceTransformer(MODEL_NAME)
        print(f"Model loaded. Embedding dimension: {model.get_sentence_embedding_dimension()}")
    return model


@app.route('/health', methods=['GET'])
def health():
    """Health check endpoint."""
    return jsonify({'status': 'healthy', 'model': MODEL_NAME})


@app.route('/embed', methods=['POST'])
def embed():
    """Generate embeddings for text input.

    Request body:
        {
            "texts": ["text1", "text2", ...] or
            "text": "single text"
        }

    Response:
        {
            "embeddings": [[0.1, 0.2, ...], [0.3, 0.4, ...]],
            "model": "all-MiniLM-L6-v2",
            "dimension": 384
        }
    """
    data = request.get_json()

    if not data:
        return jsonify({'error': 'No JSON body provided'}), 400

    # Accept either 'texts' (array) or 'text' (single string)
    texts = data.get('texts') or [data.get('text')]

    if not texts or texts == [None]:
        return jsonify({'error': 'No text provided'}), 400

    m = get_model()
    embeddings = m.encode(texts, convert_to_numpy=True).tolist()

    return jsonify({
        'embeddings': embeddings,
        'model': MODEL_NAME,
        'dimension': m.get_sentence_embedding_dimension()
    })


@app.route('/info', methods=['GET'])
def info():
    """Get model information."""
    m = get_model()
    return jsonify({
        'model': MODEL_NAME,
        'dimension': m.get_sentence_embedding_dimension()
    })


if __name__ == '__main__':
    # Preload model
    get_model()
    app.run(host='0.0.0.0', port=8001, debug=False)
