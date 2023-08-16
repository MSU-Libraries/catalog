FROM python:3.8-alpine

RUN \
    apk update && \
    apk add mariadb-client mariadb-dev mariadb-connector-c gcc musl-dev tzdata && \
    ln -fs /usr/share/zoneinfo/America/Detroit /etc/localtime

ENV TZ=America/Detroit

COPY ./requirements.txt /app/requirements.txt

WORKDIR /app

RUN pip install -r requirements.txt

COPY . /app

RUN \
    touch /test1 && \
    chown 1234 /test1 && \
    touch /test2 && \
    chown 1000 /test2


ENTRYPOINT [ "python" ]

CMD [ "app/app.py" ]
