FROM python:3.11-slim

RUN apt-get update && apt-get install curl -y

# @todo use a requirements.txt file with fixed versions?
RUN pip install fastapi pydantic sentence_transformers uvicorn

COPY . /srv/www/html/

WORKDIR /srv/www/html/

CMD ["./server.sh"]
