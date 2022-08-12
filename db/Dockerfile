FROM bitnami/mariadb-galera:10.7

USER root
ENV DEBIAN_FRONTEND=noninteractive
RUN apt update && \
    apt install netcat-openbsd -y
USER 1001

ADD ./entrypoint/ /docker-entrypoint-initdb.d
COPY ./cloud-entrypoint.sh /cloud-entrypoint.sh
COPY ./cloud-startup.sh /cloud-startup.sh

ENTRYPOINT [ "/cloud-entrypoint.sh" ]
CMD [ "/cloud-startup.sh" ]
