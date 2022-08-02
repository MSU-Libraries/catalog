# Create a hostname for the public IP for this node machine
resource "aws_route53_record" "instance_dnsrec" {
  # Zone: aws.lib.msu.edu
  zone_id = "Z0159018169CCNUQINNQG"
  name    = "${var.instance_name}.aws.lib.msu.edu"
  type    = "CNAME"
  ttl     = "300"
  records = ["${var.arecord}"]
}
