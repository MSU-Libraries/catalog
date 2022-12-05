FROM python:3.8-alpine

RUN apk update && \
    apk add mariadb-client mariadb-dev mariadb-connector-c g++ jpeg-dev zlib-dev libjpeg make tzdata && \
    ln -fs /usr/share/zoneinfo/America/Detroit /etc/localtime

ENV TZ=America/Detroit

COPY ./requirements.txt /app/requirements.txt

WORKDIR /app

RUN pip install -r requirements.txt

COPY . /app

ENTRYPOINT [ "python" ]

CMD ["view.py" ]
