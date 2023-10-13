ARG ELASTIC_TAG

FROM docker.elastic.co/elasticsearch/elasticsearch:$ELASTIC_TAG

RUN elasticsearch-plugin install --batch analysis-icu
