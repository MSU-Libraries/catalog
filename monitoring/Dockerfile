FROM python:3.8-alpine

RUN apk update && \
    apk add mariadb-client libmariadb3 libmariadb-dev

COPY ./requirements.txt /app/requirements.txt

WORKDIR /app

RUN pip install -r requirements.txt

COPY . /app

ENTRYPOINT [ "python" ]

CMD ["view.py" ]
