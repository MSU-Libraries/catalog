terraform {
    backend "s3" {
        bucket = "msulib-terraform-states"
        key    = "catalog/catalog-${STACK_NAME}-dns.tfstate"
        region = "us-east-2"
    }
}

module "dns" {
  source = "../../module/dns"
  instance_name = "${STACK_NAME}"
  arecord = "${ROUND_ROBIN_DNS}"
  aws_region = "us-east-2"
}

output "instance-dns-record" {
  value = module.dns.instance_dnsrec
}
