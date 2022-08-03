terraform {
    backend "s3" {
        bucket = "msulib-catalog-terraform-states"
        key    = "catalog/catalog-${STACK_NAME}-dns.tfstate"
        region = "us-east-2"
    }
}

module "dns" {
  source = "../../module/dns"
  instance_name = "${STACK_NAME}"
  arecord = "catalog.aws.lib.msu.edu"
}

output "instance-dns-record" {
  value = module.dns.instance_dnsrec
}
