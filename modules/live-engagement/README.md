# Live Engagement Module for UNILIS

A production-ready, standalone live engagement module for the UNILIS Learning Management System. Enables real-time interactive sessions with presentations, polls, quizzes, word clouds, whiteboards, and comprehensive analytics.

## Features

### 📊 Live Presentations
- Create and manage slide-based presentations
- Upload PDF, PowerPoint, images, and videos
- Real-time slide navigation synchronized with participants
- Presenter mode with visual slide previews, speaker notes, and a projector toggle
- Live presenter sidebar for participant questions, raised hands, and reaction counts
- Drawing tools, annotations, and laser pointer
- Fullscreen mode and presentation timer

### 📋 Polls
- Multiple Choice, True/False, Yes/No
- Rating scales and Likert surveys
- Opinion polls with instant results
- Anonymous polling option
- Real-time bar and pie chart visualization

### 🧠 Quizzes
- Timed quizzes with automatic scoring
- Multiple question types (MCQ, True/False, Short Answer)
- Leaderboard with rankings
- Attempt tracking and review
- Detailed statistics and pass rates

### ☁️ Word Cloud
- Live word submissions from participants
- Real-time visualization with weighted display
- Configurable word limits and filtering
- Moderation support for inappropriate content

### 💬 Open Responses
- Paragraph and short-answer responses
- Moderation queue for approval
- Anonymous submission option
- Character limits and validation

### 🎨 Whiteboard
- Full drawing canvas with multiple tools
- Shapes, text, highlights, and eraser
- Collaborative mode for group work
- Object-based architecture for real-time sync

### 📈 Reports & Analytics
- Comprehensive session reports
- Attendance tracking and participation metrics
- Poll and quiz statistics
- Engagement scoring
- Export to HTML/PDF

## Architecture

```
modules/live-engagement/
├── api/                    # REST API endpoints
│   ├── session.php         # Session CRUD, participants, reactions
│   ├── poll.php            # Poll management and voting
│   ├── quiz.php            # Quiz creation and attempts
│   └── activity.php        # Word cloud, responses, whiteboard
├── assets/
│   ├── css/
│   │   └── live-engagement.css  # Complete stylesheet with dark mode
│   ├── js/
│   │   └── live-engagement.js   # Modular JavaScript API client
│   └── images/
├── config/
│   ├── module.php               # Central configuration
│   └── database_helper.php      # Database access layer
├── controllers/             # (Future - MVC controllers)
├── components/              # Reusable UI components
├── database/
│   ├── install.php          # Safe table installer (idempotent)
│   └── update.php           # Schema migration system
├── helpers/
│   ├── security_helper.php  # CSRF, XSS, input validation
│   └── session_helper.php   # Session, participant, stats operations
├── models/                  # Domain models with CRUD
│   ├── BaseModel.php        # Abstract CRUD base class
│   ├── SessionModel.php     # Live session lifecycle
│   ├── PollModel.php        # Polls with options
│   ├── QuizModel.php        # Quizzes, questions, attempts
│   ├── WordCloudModel.php   # Word cloud + open responses + whiteboard
│   └── PresentationModel.php # Presentations and slides
├── services/                # Business logic layer (extensible)
├── uploads/
│   ├── presentations/       # Uploaded presentation files
│   └── temp/                # Temporary processing files
├── storage/                 # Persistent storage
├── views/
│   ├── dashboard.php        # Lecturer dashboard
│   ├── join.php             # Student join page
│   ├── presenter.php        # (Future) Presenter control panel
│   ├── session.php          # (Future) Student session view
│   └── report.php           # (Future) Session reports
├── bootstrap.php            # Module initializer
├── index.php                # Router entry point
├── .htaccess                # Apache security
└── README.md                # This file
```

## Installation

### 1. Database Setup

Access the installer via browser or run from PHP:

```php
// Include in any installer script
require_once 'modules/live-engagement/database/install.php';

// Or run directly:
// Visit: modules/live-engagement/database/install.php
```

The installer is **safe to run multiple times**. It checks if tables exist before creating them.

Live Q&A requests and emoji reactions are passed between browsers using
short-lived files under `modules/live-engagement/storage/live-room-state/`;
they are not written to the database. Ensure the web-server account can write
to `modules/live-engagement/storage/`. Q&A entries expire one hour after the
request, reaction events expire after two minutes, and ending or deleting a
session clears its temporary room state.

### 2. Directory Permissions

Ensure the following directories are writable by the web server:
- `assets/uploads/live_presentations/`
- `assets/uploads/live_presentations/temp/`
- `modules/live-engagement/storage/`

### 3. Integration

The module integrates with existing UNILIS modules:

#### Meeting Integration
```php
// In your meeting controller, when creating a meeting:
$sessionModel = new \LE\Models\SessionModel();
$sessionModel->createFromMeeting($meetingData);
```

#### Course/Unit Integration
```php
// Display sessions under a course page:
$sessionModel = new \LE\Models\SessionModel();
$sessions = $sessionModel->getByCourse($courseId);
$unitSessions = $sessionModel->getByUnit($unitId);
```

## Usage

### For Lecturers

1. Navigate to `modules/live-engagement/index.php?page=dashboard`
2. Click "New Session" and configure your session
3. Share the generated session code with students
4. Click on the session card to open the presenter view
5. Use polls, quizzes, and whiteboard during the session
6. End the session to generate reports

### For Students

1. Navigate to `modules/live-engagement/index.php?page=join`
2. Enter the session code provided by your lecturer
3. Join and participate in polls, quizzes, and activities
4. Use reactions, hand raise, and notes during the session
5. Request permission from the lecturer before typing and submitting a question

## API Reference

### Session Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `session.php?action=list` | Get user's sessions |
| GET | `session.php?action=view&id=X` | Get session details |
| GET | `session.php?action=check&code=XXX` | Check session code |
| POST | `session.php` (action=create) | Create session |
| POST | `session.php` (action=join) | Join session |
| POST | `session.php` (action=start) | Start session |
| POST | `session.php` (action=end) | End session |
| POST | `session.php` (action=raise_hand) | Raise or lower a joined participant's hand |
| POST | `session.php` (action=reaction) | Send reaction |
| POST | `session.php` (action=question_request) | Request permission to ask a question |
| POST | `session.php` (action=question_allow) | Allow a student's question (session lecturer/admin only) |
| POST | `session.php` (action=question_submit) | Submit a question after approval |
| GET | `session.php?action=questions&id=X` | List pending and submitted questions (session lecturer/admin only) |
| DELETE | `session.php?action=delete&id=X` | Delete session |

### Poll Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `poll.php?action=list&session_id=X` | Get session polls |
| GET | `poll.php?action=results&id=X` | Get poll results |
| POST | `poll.php` (action=create) | Create poll |
| POST | `poll.php` (action=activate) | Activate poll |
| POST | `poll.php` (action=vote) | Submit vote |

### Quiz Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `quiz.php?action=list&session_id=X` | Get quizzes |
| GET | `quiz.php?action=leaderboard&id=X` | Get leaderboard |
| POST | `quiz.php` (action=create) | Create quiz |
| POST | `quiz.php` (action=start_attempt) | Start attempt |
| POST | `quiz.php` (action=complete_attempt) | Complete attempt |

## JavaScript API

The module provides a comprehensive JavaScript API via the `LiveEngagement` global object:

```javascript
// Initialize
LiveEngagement.init({ sessionId: 123, isPresenter: true });

// Sessions
await LiveEngagement.createSession({ title: 'My Session' });
await LiveEngagement.joinSession('ABC123', 'Student Name');

// Polls
await LiveEngagement.createPoll({ question: '...', session_id: 1 }, ['A', 'B', 'C']);
await LiveEngagement.votePoll(pollId, optionId);

// Quizzes
await LiveEngagement.startQuiz(quizId);
await LiveEngagement.submitAnswer(attemptId, questionId, answerId);
const results = await LiveEngagement.completeQuiz(attemptId);

// UI
LiveEngagement.showToast('Success!', 'success');
const modal = LiveEngagement.showModal('<p>Content</p>', { title: 'Modal', size: 'lg' });

// Polling
LiveEngagement.startPolling('participants', (data) => updateUI(data));
LiveEngagement.stopPolling();
```

## Security

- **CSRF Protection**: All state-changing requests require a valid CSRF token
- **XSS Prevention**: Output escaping via `le_escape()` helper
- **SQL Injection**: All queries use prepared statements via `DatabaseHelper`
- **Authentication**: Reuses existing UNILIS session-based authentication
- **Authorization**: Role-based access control (lecturer/admin for management)
- **File Uploads**: MIME type validation and size limits
- **Rate Limiting**: Session-based rate limiting for API endpoints

## Database Tables

| Table | Description |
|-------|-------------|
| `live_sessions` | Core session records |
| `live_presentations` | Uploaded presentations |
| `presentation_slides` | Individual slides |
| `live_participants` | Session participants and tracking |
| `storage/live-room-state/*.state` | Temporary, file-backed Q&A and reaction events |
| `live_polls` | Poll questions |
| `live_poll_options` | Poll answer options |
| `live_poll_responses` | Participant poll responses |
| `live_quizzes` | Quiz configurations |
| `quiz_questions` | Quiz questions |
| `quiz_answers` | Question answer options |
| `quiz_attempts` | User quiz attempts |
| `quiz_attempt_answers` | Individual answer records |
| `live_wordcloud` | Word cloud prompts |
| `wordcloud_submissions` | Word submissions with weights |
| `live_open_responses` | Open-ended question prompts |
| `open_response_submissions` | Participant responses |
| `live_notes` | User session notes |
| `live_whiteboards` | Whiteboard canvases |
| `whiteboard_objects` | Drawn objects on whiteboards |
| `live_reports` | Generated session reports |
| `live_statistics` | Engagement statistics snapshots |

## Dependencies

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- UNILIS base system (Authentication, Courses modules)
- Modern browser with ES6+ support (for JavaScript)

## Development

### Coding Standards

- Follow PSR-1 and PSR-4 coding standards
- Use typed properties and return types (PHP 8+)
- Keep functions small and focused
- Document complex logic with PHPDoc
- Use namespace `LE\Models`, `LE\Services`, etc.

### Adding New Features

1. Create model class in `models/` extending `BaseModel`
2. Ensure the configured database already contains the schema required by the feature.
3. Create API endpoint in `api/`
4. Add JavaScript methods in `assets/js/live-engagement.js`
5. Create view template in `views/`
6. Register route in `index.php`

Database installation and migration scripts are not included in this repository.

## Extending

The module is designed to be extended:

- **Services Layer**: Add business logic in `services/` directory
- **Components**: Create reusable UI components in `components/`
- **Custom Reports**: Extend `ReportModel` for custom analytics
- **Presentations**: Add PDF/PowerPoint parsing in services

## License

Part of UNILIS Learning Management System.