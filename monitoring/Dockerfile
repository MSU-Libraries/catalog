FROM python:3.10-alpine

ENV PYTHONUNBUFFERED=1

RUN \
    apk update && \
    apk add mariadb-client mariadb-dev mariadb-connector-c gcc musl-dev tzdata patch && \
    ln -fs /usr/share/zoneinfo/America/Detroit /etc/localtime

ENV TZ=America/Detroit

COPY ./requirements.txt /app/requirements.txt

WORKDIR /app

RUN pip install -r requirements.txt && \
    # When upgrading to aiohttp 3.10.2, it breaks because a method was
    # deprecated in a dependency but not yet updated in the library
    # https://github.com/aio-libs/aiohttp/issues/8555
    wget https://github.com/user-attachments/files/16470284/0001-Restore-the-ability-to-create-objects-without-a-runn.patch && \
    cd /usr/local/lib/python3.10/site-packages && \
    patch -p1  < /tmp/0001-Restore-the-ability-to-create-objects-without-a-runn.patch

COPY . /app

CMD [ "python", "app/app.py" ]
