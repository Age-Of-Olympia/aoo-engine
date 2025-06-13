# CI/CD Documentation

## Overview

This project uses GitLab CI/CD for automated testing, code quality checks. The pipeline consists of several stages:
- Build: Creates Docker images
- Stan: Static analysis
- Test: Unit tests with coverage
- Security: SQLMap security tests


## Local Development

### Prerequisites

- Docker
- Make
- Git
- Composer
- Python (for SQLMap)

### Setting Up Local Environment

 Install dependencies:
```bash
composer install
```

### Running Tests Locally

Use Make commands to run various tests:

```bash
# Run tests
make test

# Run test wiht filter
make testf "TestClassName"

# Run tests with coverage
make coverage

# Run PHPStan analysis
make phpstan

# Run all tests, coverage, and PHPStan
make all

# SqlMap login security testing
make sqlmap-login

# SqlMap register security testing
make sqlmap-register
```

## CI/CD Pipeline Structure

### Templates

#### PHP Job Template
```yaml
.php-job-template:
  cache:
    key:
      files:
        - composer.lock
    paths:
      - vendor/
  tags:
    - docker-aoo
  image: $CI_REGISTRY_IMAGE/test:latest
```
This template provides:
- Dependency caching using composer.lock
- Docker image configuration
- Runner tags

### Jobs

#### Build Image Job
Builds and manages Docker images for testing:
- Checks if image exists
- Rebuilds on Dockerfile changes
- Pushes to GitLab registry

```yaml
build_image:
  stage: build
  services:
    - docker:dind
```

#### Test Job
Runs PHPUnit tests with coverage reporting:
```yaml
test_job:
  stage: test
  script:
    - make test-ci
```

#### Stan Job
Performs static analysis using PHPStan:
```yaml
stan_job:
  stage: stan
  script:
    - make phpstan-ci
```

## Makefile Commands

| Command | Description |
|---------|------------|
| `make test` | Runs PHPUnit tests |
| `make testf "TestClassName"` | Runs PHPUnit tests with a specific filter |
| `make test-ci` | Runs tests with coverage for CI |
| `make phpstan` | Runs PHPStan analysis |
| `make phpstan-ci` | Runs PHPStan for CI environment |
| `make coverage` | Generates coverage report |
| `make all` | Runs all tests, coverage, and PHPStan analysis |
| `setup-ci-env` | Sets up app for run in the ci |
| `sqlmap-login` | SQLMap login security testing |
| `sqlmap-register` | SQLMap register security testing |


## Docker Configuration

The project uses a custom PHP 8.3 image with:
- Composer
- Xdebug for coverage
- Required PHP extensions
- Build tools (git, zip, make)

### Building Docker Image Locally

```bash
docker build -t test-image -f gitlab-ci/Dockerfile.test .
```


## Pipeline Workflow

```mermaid
graph TD
    Start[Push to Repository] --> CheckImage{Docker Image Exists?}

    %% Build Stage
    subgraph "Build Stage"
        CheckImage -->|No| BuildNew[Build New Image]
        CheckImage -->|Yes| CheckChanges{Dockerfile Changes?}
        CheckChanges -->|Yes| RebuildImage[Rebuild Image]
        CheckChanges -->|No| SkipBuild[Skip Build]
        
        BuildNew --> PushRegistry[Push to Registry]
        RebuildImage --> PushRegistry
    end

    %% Image Distribution (Shared)
    subgraph "Image"
        PushRegistry --> UseImage{Pull Built Image}
    end

    %% Stan Stage
    subgraph "Stan Stage"
        UseImage --> StanJob[Run Static Analysis]
        StanJob --> RunPHPStan[Execute PHPStan]
        StanJob --> GenerateStanReport[Generate PHPStan Report]
    end

    %% Test Stage
    subgraph "Test Stage"
        UseImage --> TestJob[Run Tests]
        TestJob --> RunPHPUnit[Execute PHPUnit]
        TestJob --> GenerateCoverage[Generate Coverage Report]
    end

    %% Security Stage (manual)
    subgraph "Security Stage"
        UseApp{Run app in CI}
        UseApp --> SecurityTest[Run SQLMap Security Test manual]
        GenerateCoverage --> SecurityTest
        SecurityTest --> GenerateRaportOnFailure
    end

    %% Annotations for when:on_success
    classDef onSuccess fill:#dff0d8,stroke:#3c763d,color:#3c763d,font-weight:bold;
    class StanJob,TestJob,SecurityTest onSuccess;

    StanJob ---|only runs on_success| TestJob
    TestJob ---|only runs on_success| SecurityTest

```

### Pipeline Stages Explanation

1. **Build Stage**:
   - Checks for existing Docker image
   - Builds or rebuilds based on changes
   - Manages image registry

2. **Stan Stage** (Sequential after Build):
   - Uses built image
   - Runs PHPStan static analysis
   - Reports code quality issues
   - **Rules**: `when: on_success` - Only runs if build succeeds

3. **Test Stage** (Sequential after Stan):
   - Uses same built image
   - Runs PHPUnit tests
   - Generates coverage reports
   - **Rules**: `when: on_success` - Only runs if stan succeeds

4. **Security Stage** (Sequential after Test):
   - Uses same built image
   - Sets up application environment
   - Runs SQLMap security tests
   - Generates security reports
   - **Rules**: `when: on_success` - Only runs if tests succeed