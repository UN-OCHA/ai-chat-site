import os
from fastapi import FastAPI
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
from typing import List

model_name = os.environ.get('MODEL_NAME', 'all-MiniLM-L6-v2')
model_folder = os.environ.get('MODEL_FOLDER', '/opt/models')
model = SentenceTransformer(model_name_or_path=model_name, cache_folder=model_folder)
model.max_seq_length = 256

app = FastAPI()

class BedrockRequest(BaseModel):
    inputText: str

class BedrockResponse(BaseModel):
    embedding: List[float]

# Mimic the Bedrock embeddings endpoint.
@app.post("/bedrock/model/{model}/invoke")
def embed(request: BedrockRequest) -> BedrockResponse:
    embedding = model.encode(request.inputText)
    return BedrockResponse(embedding=embedding)

class OpenAiEmbedding(BaseModel):
    embedding: List[float]
    index: int
    object: str

class OpenAiUsage(BaseModel):
    prompt_tokens: int
    total_tokens: int

class OpenAiRequest(BaseModel):
    input: List[str]
    model: str

class OpenAiResponse(BaseModel):
    data: List[OpenAiEmbedding]
    object: str
    model: str
    usage: OpenAiUsage

# Mimic the Azure OpenAI embeddings endpoint.
@app.post("/openai/deployments/{deployment}/embeddings")
def embed(request: OpenAiRequest) -> OpenAiResponse:
    embeddings = model.encode(request.input)
    usage=OpenAiUsage(prompt_tokens=1,total_tokens=1)
    data = [OpenAiEmbedding(embedding=embedding, index=index, object="embedding") for index, embedding in enumerate(embeddings)]
    return OpenAiResponse(data=data, object="list", model=request.model, usage=usage)
