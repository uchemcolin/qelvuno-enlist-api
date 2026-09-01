================================================================================
QELVUNO ENLIST API
README.md
================================================================================

# Qelvuno Enlist API

Backend API for the first phase of the Qelvuno candidate onboarding platform.

Qelvuno is designed around a structured two-phase workflow:

    PHASE 1
    ENLIST
    Candidate intake, identity/eligibility verification and account preparation

                         |
                         v

    PHASE 2
    DOCUMENTATION
    Structured employee information and document completion

The `qelvuno-enlist-api` repository represents the first phase of that workflow.

The system is designed to provide a controlled backend process for collecting
candidate information, validating records, preparing users for the next phase,
and maintaining a reliable transition into the documentation stage.


================================================================================
1. PROJECT OVERVIEW
================================================================================

Qelvuno Enlist API is a RESTful backend service responsible for the initial
candidate onboarding stage of a larger employee lifecycle workflow.

The purpose of this phase is to establish a verified candidate record before
the candidate proceeds to the second phase of the platform.

The first phase focuses on:

- Candidate intake
- Identity and contact information
- Candidate verification
- Reference/identifier validation
- Initial profile creation
- Account preparation
- Authentication support
- Candidate status tracking
- Controlled transition to the documentation phase
- Integration with downstream services where required

The API is designed to keep the initial onboarding process separate from the
more extensive documentation workflow handled by Qelvuno Documentation API.


================================================================================
2. QELVUNO TWO-PHASE WORKFLOW
================================================================================

Qelvuno separates the employee onboarding process into two logical stages.

PHASE 1 — ENLIST
----------------

The candidate enters the system and goes through the initial onboarding and
verification process.

Typical responsibilities include:

    Candidate Intake
            |
            v
    Identity / Record Verification
            |
            v
    Candidate Profile Creation
            |
            v
    Account Preparation
            |
            v
    Eligibility for Documentation
            |
            v
    Documentation Phase


PHASE 2 — DOCUMENTATION
-----------------------

After the initial enlistment/verification stage has been completed, the
candidate moves to the Qelvuno Documentation API.

The second phase handles the detailed employee documentation workflow,
including:

- Personal information
- Addresses
- References
- Associates
- Residence history
- Next of kin
- Education
- Work experience
- Professional information
- Financial information
- Supporting documents
- Progress tracking
- Final review


The two repositories therefore represent two connected but independently
maintainable backend services.

    qelvuno-enlist-api
            |
            | Candidate completes initial onboarding
            v
    qelvuno-documentation-api


================================================================================
3. PROJECT GOALS
================================================================================

The primary goals of the Enlist API are:

1. Establish a consistent candidate intake process.

2. Validate candidate records before downstream processing.

3. Keep initial onboarding concerns separate from detailed documentation.

4. Provide a secure backend interface for candidate-related operations.

5. Support authenticated access where required.

6. Maintain clear application state and progression.

7. Prepare verified users for the documentation phase.

8. Provide a clean integration boundary between onboarding and documentation.

9. Make the system suitable for integration with web or mobile clients.

10. Keep business logic organized inside dedicated application services rather
    than placing complex workflows directly inside controllers.


================================================================================
4. ARCHITECTURE
================================================================================

The application follows a client-server architecture.

CLIENT
------
A frontend application communicates with the API through HTTP requests.

                    +----------------------+
                    |    Web / Mobile UI   |
                    +----------+-----------+
                               |
                               | HTTPS / REST
                               v
                    +----------------------+
                    |   Qelvuno Enlist API  |
                    +----------+-----------+
                               |
             +-----------------+-----------------+
             |                 |                 |
             v                 v                 v
        Authentication     Business Logic     Database
             |                 |                 |
             +-----------------+-----------------+
                               |
                               v
                    Downstream Integration
                    / Documentation Phase


BACKEND LAYER
-------------

The backend is responsible for:

- Request validation
- Authentication
- Authorization
- Business rules
- Database persistence
- Candidate state management
- Record verification
- API responses
- Integration with related services


================================================================================
5. TECHNOLOGY STACK
================================================================================

BACKEND
-------

- PHP
- Laravel
- RESTful API architecture
- Laravel Sanctum where token-based authentication is required

DATABASE
--------

- MySQL / MariaDB
- Eloquent ORM

AUTHENTICATION
--------------

- Laravel Sanctum
- API token authentication
- Secure password hashing

APPLICATION STRUCTURE
---------------------

- Controllers
- Services
- Models
- Form Requests
- API Resources
- Middleware
- Database migrations
- Seeders
- Jobs where asynchronous processing is required

DEPLOYMENT
----------

The application can be deployed using either:

- Traditional PHP/Laravel hosting
- Docker
- Nginx + PHP-FPM
- MySQL/MariaDB


================================================================================
6. CORE BACKEND PRINCIPLES
================================================================================

The application follows several backend engineering principles.

SEPARATION OF CONCERNS
----------------------

HTTP controllers are kept focused on request handling while application
services contain business logic.

VALIDATION
----------

Incoming data is validated before it reaches persistence or business logic.

AUTHENTICATION
--------------

Protected operations require an authenticated user where appropriate.

IDEMPOTENT OPERATIONS
---------------------

Where practical, update operations are designed to safely update an existing
candidate record without creating unnecessary duplicates.

TRANSACTIONAL OPERATIONS
------------------------

Operations that modify multiple related records can be grouped into database
transactions to reduce the risk of partially completed workflows.

SECURITY
--------

Sensitive values such as passwords and application secrets are kept outside
source control and supplied through environment configuration.

INTEGRATION BOUNDARIES
----------------------

The enlistment phase is intentionally separated from the detailed
documentation phase so that each service can evolve independently.


================================================================================
7. TYPICAL CANDIDATE LIFECYCLE
================================================================================

A simplified lifecycle looks like this:

    01. Candidate enters the system
            |
            v
    02. Initial candidate information is captured
            |
            v
    03. Existing records / identifying information are checked
            |
            v
    04. Candidate identity or eligibility is verified
            |
            v
    05. Candidate account is prepared
            |
            v
    06. Candidate becomes eligible for the documentation stage
            |
            v
    07. Candidate proceeds to Qelvuno Documentation API


================================================================================
8. API DESIGN
================================================================================

The API follows REST conventions.

Typical HTTP methods include:

    GET
        Retrieve resources

    POST
        Create resources or execute actions

    PUT
        Replace/update resources

    PATCH
        Partially update resources

    DELETE
        Remove resources where permitted


Responses use standard HTTP status codes.

    200    Successful request
    201    Resource created
    400    Bad request
    401    Authentication required / invalid credentials
    403    Authenticated but not permitted
    404    Resource not found
    409    Conflict
    422    Validation error
    500    Internal server error


================================================================================
9. AUTHENTICATION
================================================================================

Where authentication is enabled, the API uses token-based authentication.

A typical flow is:

    Client
       |
       | Login / authentication request
       v
    Enlist API
       |
       | Validate credentials
       v
    Authentication service
       |
       | Token
       v
    Client
       |
       | Authorization: Bearer <token>
       v
    Protected API endpoints


Passwords should never be stored in plaintext.

Environment-specific secrets must be supplied through `.env` or the hosting
environment and must never be committed to Git.


================================================================================
10. DATA VALIDATION
================================================================================

Validation is an important part of the enlistment stage.

The backend validates:

- Required candidate information
- Identifying information
- Contact information
- Authentication data
- Candidate identifiers
- Duplicate records
- Workflow eligibility
- Data formats
- State transitions

Validation errors should be returned in a predictable structure so that a
frontend can display useful feedback to the candidate.


================================================================================
11. DATABASE DESIGN
================================================================================

The database layer is designed around candidate and account information.

Typical responsibilities include:

- User/account records
- Candidate information
- Verification information
- Candidate identifiers
- Contact information
- Application status
- Authentication tokens
- Audit-related information where implemented

Relationships should be represented using Laravel Eloquent models rather than
duplicating persistence logic throughout controllers.


================================================================================
12. SERVICE-ORIENTED DESIGN
================================================================================

Business operations should be organized into dedicated services.

Examples of service responsibilities include:

Candidate Service
-----------------
Responsible for candidate creation, retrieval and updates.

Verification Service
--------------------
Responsible for validation of candidate records and eligibility checks.

Authentication Service
----------------------
Responsible for authentication-related operations.

Profile Service
---------------
Responsible for candidate profile information.

Transition Service
------------------
Responsible for determining whether a candidate can proceed to the
documentation phase.

Notification Service
--------------------
Responsible for application notifications where required.


================================================================================
13. ERROR HANDLING
================================================================================

The API should return meaningful HTTP status codes and structured responses.

Example validation response:

{
    "message": "The given data was invalid.",
    "errors": {
        "phone": [
            "The phone field is required."
        ]
    }
}


Example unauthorized response:

{
    "message": "Unauthenticated."
}


Example not-found response:

{
    "message": "Candidate not found."
}


The exact response structure may vary by endpoint and application version.


================================================================================
14. ENVIRONMENT CONFIGURATION
================================================================================

A typical `.env` configuration may contain:

APP_NAME="Qelvuno Enlist API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qelvuno_enlist
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

SANCTUM_STATEFUL_DOMAINS=localhost

MAIL_MAILER=log
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"


IMPORTANT:

Never commit the real `.env` file.

Use `.env.example` for safe configuration documentation.


================================================================================
15. INSTALLATION
================================================================================

REQUIREMENTS
------------

- PHP 8.2+ or the version specified by composer.json
- Composer
- MySQL or MariaDB
- Node.js/npm if frontend assets are included
- Git
- Docker (optional)


CLONE THE PROJECT
-----------------

git clone https://github.com/uchemcolin/qelvuno-enlist-api.git

cd qelvuno-enlist-api


INSTALL PHP DEPENDENCIES
------------------------

composer install


CREATE ENVIRONMENT FILE
-----------------------

cp .env.example .env


GENERATE APPLICATION KEY
------------------------

php artisan key:generate


CONFIGURE DATABASE
------------------

Update `.env` with your local database credentials.


RUN MIGRATIONS
--------------

php artisan migrate


OPTIONAL SEEDING
----------------

php artisan db:seed


START THE APPLICATION
---------------------

php artisan serve


The API will normally be available at:

http://localhost:8000


================================================================================
16. DOCKER
================================================================================

If Docker configuration is included in the repository, the application can be
run using Docker Compose.

Typical commands:

    docker compose build

    docker compose up -d

    docker compose down

    docker compose logs -f

    docker compose exec app php artisan migrate


The exact container configuration depends on the deployment files included in
the project.


================================================================================
17. TESTING
================================================================================

Tests can be executed with Laravel's testing tools.

Examples:

    php artisan test

or:

    composer test


A good test suite should cover:

- Authentication
- Candidate creation
- Validation
- Duplicate handling
- Verification logic
- Authorization
- Workflow transitions
- Database relationships
- API response structures


================================================================================
18. SECURITY
================================================================================

Security considerations include:

- Password hashing
- Token-based authentication
- Request validation
- Authorization middleware
- Rate limiting
- Environment-based secrets
- Secure database credentials
- Protection against mass assignment
- Controlled file/data input
- HTTPS in production

Production deployments should:

- Disable APP_DEBUG
- Use HTTPS
- Use strong database credentials
- Restrict database access
- Protect environment variables
- Rotate exposed secrets immediately
- Configure appropriate CORS policies
- Configure rate limiting
- Monitor application logs


================================================================================
19. PRODUCTION CHECKLIST
================================================================================

Before deploying:

[ ] APP_ENV configured correctly
[ ] APP_DEBUG=false
[ ] APP_KEY generated
[ ] Production database configured
[ ] Database migrations executed
[ ] HTTPS enabled
[ ] CORS configured
[ ] Rate limiting configured
[ ] Mail configuration verified
[ ] Queue workers configured if required
[ ] Storage permissions checked
[ ] Logs monitored
[ ] Secrets removed from source code
[ ] `.env` excluded from Git
[ ] API endpoints tested
[ ] Authentication tested
[ ] Error handling tested


================================================================================
20. PROJECT RELATIONSHIP
================================================================================

Qelvuno is intentionally divided into two major backend phases.

PHASE 1
-------

Repository:

qelvuno-enlist-api

Purpose:

Candidate intake, verification and account preparation.


PHASE 2
-------

Repository:

qelvuno-documentation-api

Purpose:

Detailed employee documentation and final profile completion.


The first phase establishes the candidate.

The second phase completes the candidate's structured employee record.


================================================================================
21. PROJECT STATUS
================================================================================

This repository represents the backend component of the Qelvuno Enlist phase.

The project is intended to demonstrate:

- REST API development
- Laravel backend engineering
- Authentication
- Validation
- Database-driven workflows
- Service-oriented application design
- Candidate lifecycle management
- Integration between independent backend services


================================================================================
22. AUTHOR
================================================================================

Tochukwu Uchem

Email:
uchemcolin@gmail.com

================================================================================
23. MySQL onboarding query
================================================================================

MySQL Query to onboarding someone from enlist to documentation 
to complete their onboarding process. All tables are from the legacy 
tables, all except onboardingreport_test_2 has a few 
schema modifications to be able to work with the revamping.

SELECT *
FROM recruitment_sps_test_2.personal_info
WHERE referenceNo = 'FIRS-IA-0726F7E2DC7F';

SELECT *
FROM recruitment_documentation_test_2.personal_info
WHERE referenceNo = 'FIRS-IA-0726F7E2DC7F';

INSERT INTO recruitment_documentation_test_2.personal_info
(
firstName,
surname,
middleName,
referenceNo,
passportPhotograph,
title,
maidenName,
dateOfBirth,
placeOfBirth,
birthCertificate,
gender,
state_of_origin,
local_govt,
nationality,
nin,
phoneNo,
email
)
SELECT
firstName,
surname,
middleName,
referenceNo,
passportPhotograph,
title,
maidenName,
dateOfBirth,
placeOfBirth,
birthCertificate,
gender,
state_of_origin,
local_govt,
nationality,
nin,
phoneNo,
email
FROM recruitment_sps_test_2.personal_info
WHERE referenceNo = 'FIRS-IA-0726F7E2DC7F';

SELECT *
FROM recruitment_documentation_test_2.personal_info
WHERE referenceNo = 'FIRS-IA-0726F7E2DC7F';

INSERT INTO onboardingreport_test_2.onboarded
(reference)
VALUES
('FIRS-IA-0726F7E2DC7F');

INSERT INTO onboardingreport_test_2.staff
(ir, name)
VALUES
('28775', 'John Doe');

INSERT INTO onboardingreport_test_2.transfer_log
(reference, ir, done_by)
VALUES
(
'FIRS-IA-0726F7E2DC7F',
'28775',
'John Doe'
);

SELECT *
FROM onboardingreport_test_2.transfer_log
WHERE reference = 'FIRS-IA-0726F7E2DC7F';

SELECT *
FROM onboardingreport_test_2.onboarded
WHERE reference = 'FIRS-IA-0726F7E2DC7F';

SELECT *
FROM recruitment_documentation_test_2.personal_info
WHERE referenceNo = 'FIRS-IA-0726F7E2DC7F';

================================================================================
24. API DOCUMENTATION — LARAVEL SCRAMBLE
================================================================================

Laravel Scramble is used to automatically generate API documentation for the
Qelvuno Enlist API.

The documentation provides an interactive view of the available API endpoints,
including:

- API routes
- HTTP methods
- Request parameters
- Request bodies
- Validation rules
- Response structures
- Authentication requirements
- API schemas


ACCESSING THE API DOCUMENTATION
-------------------------------

When running the application locally with:

    php artisan serve

the Laravel Scramble documentation can be accessed at:

    http://localhost:8000/docs/api


OPENAPI SPECIFICATION
---------------------

The generated OpenAPI specification can be accessed at:

    http://localhost:8000/docs/api.json


PRODUCTION
----------

When the application is deployed to a production server, replace the local
host address with the application's domain.

Example:

    https://yourdomain.com/docs/api

OpenAPI specification:

    https://yourdomain.com/docs/api.json


USAGE
-----

The Scramble documentation can be used by frontend developers, mobile
developers, testers and backend developers to understand and test the API
without manually inspecting every route and controller.

The documentation should remain synchronized with the application's API
implementation.

If API routes, request validation, controllers, resources or response
structures change, the generated documentation should be checked to ensure
that it accurately represents the current API.


SECURITY
--------

If the API documentation exposes internal endpoints or sensitive information,
access to the documentation should be restricted appropriately in production.

================================================================================
END OF QELVUNO ENLIST API README
================================================================================