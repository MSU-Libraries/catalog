# Domain name created during provisioning
output "instance_dnsrec" {
  description = "AWS Hostname"
  value       = aws_route53_record.instance_dnsrec.fqdn
}

