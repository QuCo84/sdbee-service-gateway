<?php

$yaml = "steps:
- name: 'gcr.io/google.com/cloudsdktool/cloud-sdk'
  args:
  - gcloud
  - functions
  - deploy
  - {$argv[0]}
  - --region={$argv[1]}
  - --source=.
  - --trigger-http
  - --runtime=php81
  ";
  file_put_contents( 'cloudbuild.yaml', $yaml);