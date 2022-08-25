FROM bitnami/mariadb-galera:10.7

USER root
ENV DEBIAN_FRONTEND=noninteractive
RUN apt update && \
    apt install netcat-openbsd -y
USER 1001

ARG VUFIND_VERSION

# Get the create-database SQL for the version of Vufind
RUN wget https://raw.githubusercontent.com/vufind-org/vufind/v${VUFIND_VERSION}/module/VuFind/sql/mysql.sql \
    /docker-entrypoint-initdb.d/vufind.sql && \
    sed -i '1s/^/use vufind;\n/' /docker-entrypoint-initdb.d/vufind.sql

COPY ./cloud-entrypoint.sh /cloud-entrypoint.sh
COPY ./cloud-startup.sh /cloud-startup.sh

ENTRYPOINT [ "/cloud-entrypoint.sh" ]
CMD [ "/cloud-startup.sh" ]
