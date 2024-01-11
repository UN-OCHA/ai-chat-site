import os
from fastapi import Depends, Request
from pydantic import BaseModel
from typing import List
from llama_cpp.server.app import create_app, Settings, CreateChatCompletionRequest, CreateCompletionRequest, create_chat_completion, create_completion, get_llama
from llama_cpp import ChatCompletion, Completion, Llama

settings = Settings(
    chat_format=os.environ.get('CHAT_FORMAT', 'chatml'),
    n_threads=os.environ.get('N_THREADS', 4),
    n_ctx=os.environ.get('N_CTX', 4096),
    n_batch=os.environ.get('N_BATCH', 4096),
    model_alias=os.environ.get('MODEL_ALIAS', 'gpt-3.5-turbo'),
    model=os.environ.get('MODEL', '')
)

# Server.
app = create_app(settings=settings)

class BedrockTextGenerationConfig(BaseModel):
    temperature: float
    topP: float
    maxTokenCount: int
    stopSequences: List[str]

class BedrockRequest(BaseModel):
    inputText: str
    textGenerationConfig: BedrockTextGenerationConfig

class BedrockTextGenerationResult(BaseModel):
    tokenCount: int
    outputText: str
    completionReason: str

class BedrockResponse(BaseModel):
    inputTextTokenCount: int
    results: List[BedrockTextGenerationResult]

# Mimic the Bedrock completion endpoint.
@app.router.post("/bedrock/model/{model}/invoke")
async def bedrock_invoke(
    request: Request,
    body: BedrockRequest,
    llama: Llama = Depends(get_llama)
) -> BedrockResponse:
    completion_request = CreateCompletionRequest(
        prompt=body.inputText,
        temperature=body.textGenerationConfig.temperature,
        top_p=body.textGenerationConfig.topP,
        max_tokens=body.textGenerationConfig.maxTokenCount,
        stop=body.textGenerationConfig.stopSequences
    )
    completion_response = await create_completion(
        request=request,
        body=completion_request,
        llama=llama
    )

    result = BedrockTextGenerationResult(
        tokenCount=completion_response.get('usage').get('completion_tokens'),
        outputText=completion_response.get('choices')[0].get('text'),
        completionReason=completion_response.get('choices')[0].get('finish_reason').upper(),
    )
    return BedrockResponse(
        inputTextTokenCount=completion_response.get('usage').get('prompt_tokens'),
        results=[result]
    )

# Mimic the Azure OpenAI chat completion endpoint.
@app.router.post("/openai/deployments/{deployment}/chat/completions")
async def bedrock_invoke(
    request: Request,
    body: CreateChatCompletionRequest,
    llama: Llama = Depends(get_llama)
) -> ChatCompletion:
    chat_completion_response = await create_chat_completion(request, body, llama, settings)
    return chat_completion_response

# Mimic the Azure OpenAI completion endpoint.
@app.router.post("/openai/deployments/{deployment}/completions")
async def bedrock_invoke(
    request: Request,
    body: CreateCompletionRequest,
    llama: Llama = Depends(get_llama)
) -> ChatCompletion:
    completion_response = await create_chat_completion(request, body, llama, settings)
    return completion_response

