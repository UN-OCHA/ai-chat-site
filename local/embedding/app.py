import os
from fastapi import FastAPI
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
from typing import List

model_name = os.environ.get('MODEL_NAME', 'all-MiniLM-L6-v2')
model_folder = os.environ.get('MODEL_FOLDER', '/opt/models')
model = SentenceTransformer(model_name_or_path=model_name, cache_folder=model_folder)
model.max_seq_length = 256

class Embedding(BaseModel):
    embedding: List[float]
    index: int
    object: str

class Request(BaseModel):
    input: List[str]
    model: str

class Response(BaseModel):
    data: List[Embedding]
    object: str

app = FastAPI()

# Mimic the OpenAI embeddings endpoint with the minimum required fields.
@app.post("/v1/embeddings")
def embed(request: Request):
    embeddings = model.encode(request.input)
    data = [Embedding(embedding=embedding, index=index, object="embedding") for index, embedding in enumerate(embeddings)]
    return Response(data=data, model=request.model, object="list")
