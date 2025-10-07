FROM zookeeper:3.9

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit
ENV PIPX_HOME=/opt/pipx
ENV PIPX_BIN_DIR=/usr/local/bin

# Install additional packages, including zk-shell
RUN \
    apt update && \
    apt install -y htop moreutils gettext-base jq \
                   python3 python3-distutils python3-pip pipx && \
    # Using pipx to avoid pip's 'externally-managed-environment' error
    pipx install zk-shell && \
    apt remove -y pipx python3-pip && \
    apt autoremove -y && \
    rm -rf /var/lib/apt/lists/*

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone
