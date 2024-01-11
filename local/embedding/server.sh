#!/bin/bash

uvicorn app:app \
  --host ${SERVER_HOST:-0.0.0.0} \
  --port ${SERVER_PORT:-80} \
  --reload
