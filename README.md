# Rafter 🏡

Rafter is a serverless deployment platform powered by [Google Cloud](https://cloud.google.com). It leverages Google Cloud Run (and many other tools) to transform your Git repository into a fully-scalable serverless application running in the cloud - with zero configuration.

- 💰 Scales to zero when not in use, saving you money — perfect for hobby projects
- 🔥 Automatically scales to handle load
- 🔌 Manages, connects and creates Cloud SQL databases for your applications automatically
- ⚡️ Connects to GitHub and supports deploy-on-push
- 🚀 Spin up multiple environments available at vanity URLs at the click of a button
- ✨ No Dockerfiles required

## Google Cloud Services

### Cloud Run (web service)

[Official Documentation](https://cloud.google.com/products#serverless-computing)

- Creates services for each environment of each project, automatically
- Cloud Run handles all traffic roll-out, scaling, and configuration
- Environment variables are assigned to each unique service through the API

### Cloud Build (image creation)

[Official Documentation](https://cloud.google.com/cloud-build/)

- Docker images are created when code is pushed to GitHub
- Dockerfile is automatically provided based on type of project
- Currently supported: **Laravel**

### Cloud SQL (database)

[Official Documentation](https://cloud.google.com/sql/)

- Database instances are provisioned by Rafter through the API
- Databases are created and assigned to projects automatically using the Admin API
- Environmental variables are properly assigned based on type of project

### Cloud Firestore (cache and session drivers)

[Official Documentation](https://cloud.google.com/firestore)

- NoSQL database to support key-value caching and session management
- Drivers integrated automatically based on project
- No additional credentials required for consumer apps to use, since credentials are supplied within Cloud Run

### Cloud Tasks (queue driver)

[Official Documentation](https://cloud.google.com/tasks)

- Robust queue management platform
- Queues are automatically created for each environment
- Dedicated Cloud Run service is created for each project to handle incoming jobs through HTTP request payloads
- No daemon or worker instance is required
- Since Cloud Run is serverless, instances can fan out, thousands of jobs can be processed in a matter of seconds

### Cloud Storage (image artifacts and uploads)

[Official Documentation](https://cloud.google.com/storage)

- Object storage, similar to S3
- Automatically handles uploaded artifacts from Cloud Build
- Integrated into application helpers based on project type to handle user uploads

## Development notes

- Clone it
- Use [Valet](https://laravel.com/docs/6.x/valet) to run it and connect to a local MySQL database
- Run `make share` to fire up ngrok.io local tunnel
- Requires grpc PHP extension to be installed locally: `pecl install grpc`

## Inspiration

- [Laravel Vapor](https://vapor.laravel.com/) and [Laravel Forge](https://forge.laravel.com/)
