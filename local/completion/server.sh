#!/bin/bash

# @todo handle additional parameters.
python3 -m llama_cpp.server --host 0.0.0.0 --port 80 \
  --chat_format "${CHAT_FORMAT:-chatml}" \
  --n_threads "${N_THREADS:-4}" \
  --n_ctx "${N_CTX:-4096}" \
  --n_batch "${N_BATCH:-4096}" \
  --model_alias "${MODEL_ALIAS:-gpt-3.5-turbo}"
