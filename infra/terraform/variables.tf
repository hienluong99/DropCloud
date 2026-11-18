variable "aws_region" {
  description = "AWS Region for CloudDrop deployment"
  type        = string
  default     = "ap-southeast-1"
}

variable "project_name" {
  description = "Project prefix for AWS resources"
  type        = string
  default     = "clouddrop"
}

variable "environment" {
  description = "Deployment environment (dev/staging/prod)"
  type        = string
  default     = "prod"
}
