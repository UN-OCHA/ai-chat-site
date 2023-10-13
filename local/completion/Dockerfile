FROM python:3.11-slim

RUN apt-get update && apt-get install build-essential curl git libopenblas-dev pkg-config -y

# @todo use a requirements.txt file with fixed versions?
# @todo replace with a tagged release when there is one with the chatml format.
RUN CMAKE_ARGS="-DLLAMA_BLAS=ON -DLLAMA_BLAS_VENDOR=OpenBLAS" pip install -e git+https://github.com/abetlen/llama-cpp-python.git@c305be6db68f295a772ea7ab56ee90de84b61a76#egg=llama-cpp-python[server]

COPY . /srv/www/html/

WORKDIR /srv/www/html/

CMD ["./server.sh"]
