# <a name="_dv4rs6y7d7h8"></a>**Senior Full Stack Engineer Assessment (PHP/React)**
**Objective:** Build a robust Exam Management System. 

**Context:** We are looking for an engineer who excels in Software Architecture and DevOps. While the UI requirements are straightforward, we are evaluating how you structure complex business rules, manage state, and containerize your application.
## <a name="_5btmrmqbsze9"></a>**1. The Problem (User Stories)**
We need a system where Admins can manage exams and Students can take them, subject to strict time constraints.

- **As an Admin**, I need to create exams with specific "Cooldown" periods to prevent students from spamming attempts. If I update an exam's rules, I need to ensure data consistency by resetting student histories.
- **As a Student**, I want to see if I am eligible to take an exam. If I am blocked by a cooldown, I need to know exactly when I can try again.
## <a name="_7xjlypi1cjlz"></a>**2. Functional Requirements**
### <a name="_3gd0s0bebxod"></a>**Admin Features**
**1. Create & Update Exams** Admins must be able to create and update exams with the following strict validation rules:

- **Exam Id:** Must be a GUID.
- **Title:** Can be in any language and can contain special characters.
- **Maximum Number of Attempts:** Integer between **1 to 1000**.
- **Cooldown Time:** Time between attempts in minutes (Integer between **0 to 525,600**).
- **Update Rule (Crucial):** When an existing exam is updated by an admin, **all students’ attempt history must be reset** to ensure fairness/consistency.

**2. Student Attempt History (Admin View)** Admins need a table showing past and current attempts for all students. The table must include:

- **Attempt Id** (Guid)
- **Attempt Number**
- **Status** (In Progress / Completed)
- **Start Time** (Display in **UTC**)
- **End Time** (If completed, display in **UTC**)
### <a name="_hryx0bw6yxxl"></a>**Student Features**
**1. Exam Dashboard** Students should view exam details: Title, Max Attempts, Attempts Remaining, and Cooldown Time.

**2. My Attempts History (Student View)** A table of the student's own attempts. Columns must include:

- **Attempt Number**
- **Status** (In Progress / Completed)
- **Start Time** (Display in **Current Browser TimeZone**)
- **End Time** (If completed, display in **Current Browser TimeZone**)

**3. Taking the Exam (Dynamic Logic)** The UI must dynamically handle the attempt lifecycle based on the following rules:

- **Start Conditions:** The "Start Attempt" button should only appear if the student has **attempts remaining** AND the **cooldown period** (if applicable) is satisfied.
- **In Progress:** When "Start" is clicked:
  - Change attempt state to In Progress.
  - Update Start Time.
  - Show a "Submit Attempt" button.als
- **Completion:** When "Submit" is clicked:
  - Change attempt state to Completed.
  - Update End Time.
- **Status Messages:**
  - If no attempts are left, show: *"No attempts left."*.
  - If blocked by cooldown, show: *"Your next attempt will be available at [date/time]."*.

## <a name="_f7flogvfqb9p"></a>**3. Technical Guidelines**
### <a name="_ak8fea9j0wc1"></a>**A. Infrastructure (Docker)**
We require a containerized environment to run this application.

- Provide a docker-compose.yml file.
- We should be able to spin up the entire stack (API, Database, Frontend) with a single command (e.g., docker-compose up).
- Database migrations and seeding should run automatically or via a documented command.
### <a name="_igsft779drzl"></a>**B. Backend (PHP)**
- **Framework:** Use **Symfony (6/7)**, **Slim**, or a high-quality **Vanilla PHP** structure. **Do not use Laravel.**
- **Standards:** We expect code that demonstrates seniority. Use modern PHP 8.2+ features, strict typing, and clean layering (Service/Repository patterns).
- **API:** RESTful design.
### <a name="_6xvthe7ra9lj"></a>**C. Frontend (React)**
- **Framework:** React 17+ with **TypeScript**.
- **UI:** You may use a component library (MUI, Chakra, etc.) or plain CSS. Focus on clean component composition and hook usage.
### <a name="_rktny11gpeqe"></a>**D. Quality Assurance**
- **Testing:** A senior solution must include tests. We expect both **Unit Tests** (for the backend business logic) and **Integration Tests** (for the API endpoints).
- **Git:** Please include your .git folder so we can see your commit history.
## <a name="_76lhvkuclix4"></a>**4. Acceptance Criteria**
Your submission must meet the following criteria to be considered:

- **View Switching:** Give a mechanism to switch between Admin view and Student View.
- **Data Integrity:** Only valid exam details should be saved.
- **Reset Logic:** On exam update, **all student attempts are cleared**.
- **Visibility:** Students can see the current exam and attempt status in the student view.
- **Rules Engine:** A student cannot start a new attempt unless the cooldown has passed and attempts remain.
- **State strictness:** In-progress attempts can only be submitted (cannot jump states).
- **History:** Both Admin and Student can view Attempt history in the table.
- **Testing:** Unit tests/Integration Tests are included and passing.
- **Code Quality:** The solution must represent **production quality code**. This includes:
  - Validation errors shown to the user.
  - Relevant code comments.
  - Consistent naming conventions.
  - Good design principles (extensibility).

**⚠️ Important: Submissions without API + UI tests will be marked incomplete and will not be evaluated.**
## <a name="_b1dxv8p0l3xt"></a>**5. Submission**
- **Deliverable:** A ZIP file named Firstname.Lastname.ExamApp.zip.
- **README:** Must include:
  - Prerequisites (e.g., Docker Desktop).
  - The exact command to start the app.
  - The exact command to run the tests.
### <a name="_17ne4w17o9v3"></a>**Video Submission Requirements**
A demo video is **required** for your submission to be evaluated. Submissions without a video walkthrough will be marked incomplete and rejected.
#### <a name="_5yg7izrgjj30"></a>**1. Format: Screen Recording + Face Cam**
- **Visuals:** Record your screen while demonstrating the application. Your face must be clearly visible (Face Cam) with good lighting.
- **Audio:** Your voice should be clearly audible and professional.
- **Length:** Keep it concise (approx. 5-10 minutes).
#### <a name="_acv6e69u354v"></a>**2. Application Walkthrough**
- **Startup:** Start the video by demonstrating the **Docker** startup process. Run docker-compose up and show the environment spinning up cleanly (API, DB, UI) without manual configuration.
- **Architecture:** Briefly explain your choice of PHP structure (e.g., Symfony/Slim/Vanilla), how you implemented the Service Layer, and how you enforced strict typing.
- **Functionality:** Demonstrate the critical flows:
  - Admin creating an exam with Cooldowns.
  - **Crucial:** Demonstrate the **Reset Logic** (Update an exam -> Show student history being cleared).
  - Student taking an exam and seeing the browser-local time in history.
- **Testing:** Run **all test cases** on screen:
  - Run the API tests inside the container (e.g., docker-compose run api composer test) and show the passing results.
  - Run the UI tests (npm test) and show the passing results.
#### <a name="_6p2gosmjo4yy"></a>**3. Technical Discussion**
As you navigate the app, briefly discuss your engineering decisions:

- **Security**
- **Performance**
- **Extensibility**
### <a name="_vnsqcf4jjt6a"></a>**Submission Instructions**
- **API Implementation** (functional & validated, PHP 8.2+)
- **UI Implementation** (functional & validated, React 17+)
- **API Unit/Integration Tests** (must run via Docker container using PHPUnit)
- **UI Component Tests** (must run via npm test)
- **Demo Video** (walkthrough + run all tests)
- **Application (API & UI)** executable directly via docker-compose up without requiring local PHP/Node installation.
- **README.md** included with clear, one-line steps to start the app and run the tests.
- **Database Schema** provided via Migration files or a valid SQL script for MySQL/PostgreSQL (with constraints).
- **Code** not hosted on publicly accessible repositories.
- **Submit as a ZIP file** containing source code.
- **Exclude:** /vendor, /node\_modules, .phpunit.cache
- **/src and /tests folders** included in ZIP (tests must be runnable)
- **Bonus:** Expose API with Swagger/OpenAPI, add UI E2E test coverage (Cypress/Playwright).


