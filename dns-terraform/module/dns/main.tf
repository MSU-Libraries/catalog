# Provider library needed to run
# To install: terraform init
terraform {
  required_version = ">= 1.2.3"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 4.16"
    }
  }
}

# Define the provider to use
provider "aws" {
  region = var.aws_region
}

# Create a hostname for the public IP for this node machine
resource "aws_route53_record" "instance_dnsrec" {
  # Zone: aws.lib.msu.edu
  zone_id = "Z0159018169CCNUQINNQG"
  name    = "${var.instance_name}.aws.lib.msu.edu"
  type    = "CNAME"
  ttl     = "300"
  records = ["${var.arecord}"]
}
