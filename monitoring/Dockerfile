FROM python:3.8-alpine

ENV PYTHONUNBUFFERED=1

RUN \
    apk update && \
    apk add mariadb-client mariadb-dev mariadb-connector-c gcc musl-dev tzdata && \
    ln -fs /usr/share/zoneinfo/America/Detroit /etc/localtime

ENV TZ=America/Detroit

COPY ./requirements.txt /app/requirements.txt

WORKDIR /app

RUN pip install -r requirements.txt

COPY . /app

CMD [ "python", "app/app.py" ]
