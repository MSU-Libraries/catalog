variable "instance_name" {
  description = "Name of instance to create CNAME for"
  type = string
}

variable "arecord" {
  description = "A record to link the CNAME DNS entry to"
  type = string
}

variable "aws_region" {
  description = "AWS region to connect to by default"
  type = string
}
