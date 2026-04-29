FROM python:3.10.18-alpine

ENV PYTHONUNBUFFERED=1

RUN \
    apk update && \
    apk add mariadb-client mariadb-dev mariadb-connector-c gcc musl-dev tzdata vim && \
    ln -fs /usr/share/zoneinfo/America/Detroit /etc/localtime

ENV TZ=America/Detroit

COPY ./requirements.txt /app/requirements.txt

WORKDIR /app

RUN pip install --upgrade pip && pip install -r requirements.txt

COPY . /app

CMD [ "python", "app/app.py" ]
