FROM bitnami/zookeeper:3.8

ENV TZ=America/Detroit
USER root

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

# Set volume permissions
RUN chown -R 1001:1001 /bitnami

USER 1001
