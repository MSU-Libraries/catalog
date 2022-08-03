FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit

# Perform updates
RUN apt update && \
    apt install software-properties-common curl gnupg openssh-client git python3-netaddr python3-dnspython -y

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

RUN add-apt-repository --yes --update ppa:ansible/ansible && \
    # Install Ansible
    apt install moreutils gettext-base ansible pip -y && \
    # Installing specific version of resolvelib to fix:
    # https://bugs.gentoo.org/795933 (see also: https://github.com/ansible-collections/community.digitalocean/issues/132)
    pip install -Iv 'resolvelib<0.6.0' && \
    ansible-galaxy collection install community.general ansible.posix community.docker && \
    # Install Terraform
    curl -fsSL https://apt.releases.hashicorp.com/gpg | apt-key add - && \
    add-apt-repository --yes --update "deb [arch=amd64] https://apt.releases.hashicorp.com $(lsb_release -cs) main"  && \
    apt-get install terraform

CMD bash
