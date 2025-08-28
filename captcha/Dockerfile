FROM registry.gitlab.msu.edu/msu-libraries/public/go-away:v0.7.0

RUN apk add bash vim htop && \
    mkdir /cache

COPY ./config.yml /
COPY ./policy.yml /
