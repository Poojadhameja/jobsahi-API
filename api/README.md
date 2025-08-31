# JOBSAHI API Documentation

## 🚀 Overview
JOBSAHI is a comprehensive job portal API that connects students, recruiters, and institutes. This API provides endpoints for job management, course enrollment, user authentication, and more.

## 🔧 Recent Fixes Applied
- ✅ Fixed database connection issues (PDO + MySQLi compatibility)
- ✅ Added missing JWT constants and configuration
- ✅ Fixed include path issues in authentication files
- ✅ Standardized API responses with ResponseHelper
- ✅ Added proper CORS headers
- ✅ Created configuration file for centralized settings
- ✅ Added uploads directory for file storage

## 📁 API Structure
```
api/
├── auth/           # Authentication & Authorization
├── user/           # User CRUD operations
├── jobs/           # Job management
├── courses/        # Course system
├── student/        # Student-specific features
├── jwt_token/      # JWT implementation
├── skills/         # Skills management
├── referrals/      # Referral system
├── notifications/  # Notification system
├── messages/       # Messaging system
├── certificates/   # Certificate management
├── helpers/        # Utility functions
├── config/         # Configuration files
└── uploads/        # File uploads
```

## 🛠️ Setup Instructions

### 1. Environment Configuration
Create a `.env` file in the root directory:
```env
# Database Configuration
DB_HOST=localhost
DB_DATABASE=jobsahi_db
DB_USERNAME=root
DB_PASSWORD=

# JWT Configuration
JWT_SECRET=your-super-secret-jwt-key-change-this-in-production
```

### 2. Database Setup
- Create MySQL database named `jobsahi_db`
- Import the database schema from Laravel migrations
- Update `.env` file with your database credentials

### 3. File Permissions
Ensure the `uploads/` directory is writable:
```bash
chmod 755 api/uploads/
```

## 🔐 Authentication

### Login
```http
POST /api/auth/login.php
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password123"
}
```

### Response
```json
{
    "status": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "user@example.com",
            "role": "student"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "expires_in": 86400
    }
}
```

## 📋 Available Endpoints

### Authentication
- `POST /api/auth/login.php` - User login
- `POST /api/auth/register.php` - User registration
- `POST /api/auth/logout.php` - User logout
- `POST /api/auth/verify-otp.php` - OTP verification
- `POST /api/auth/forgot-password.php` - Password reset

### User Management
- `GET /api/user/get_all_users.php` - Get all users
- `GET /api/user/get_user.php?id={id}` - Get specific user
- `POST /api/user/create_user.php` - Create new user
- `PUT /api/user/update_user.php` - Update user
- `DELETE /api/user/delete_user.php` - Delete user

### Jobs
- `GET /api/jobs/jobs.php` - Get job listings
- `GET /api/jobs/job-detail.php?id={id}` - Get job details
- `POST /api/jobs/job_apply.php` - Apply for job
- `POST /api/jobs/job_save.php` - Save job
- `DELETE /api/jobs/job_remove.php` - Remove saved job

### Courses
- `GET /api/courses/courses.php` - Get course listings
- `GET /api/courses/get-course.php?id={id}` - Get course details
- `POST /api/courses/enroll.php` - Enroll in course
- `POST /api/courses/feedback.php` - Submit course feedback

### Student Features
- `GET /api/student/dashboard.php` - Student dashboard
- `GET /api/student/applications.php` - Get job applications
- `GET /api/student/interviews.php` - Get interviews
- `POST /api/student/resume-upload.php` - Upload resume

## 🔒 Security Features

### JWT Authentication
- Token-based authentication
- Configurable expiration time
- Secure token generation and validation

### CORS Support
- Cross-origin resource sharing enabled
- Configurable allowed origins
- Preflight request handling

### Input Validation
- JSON input validation
- SQL injection prevention
- XSS protection

## 📊 Response Format

All API responses follow a standardized format:

### Success Response
```json
{
    "status": true,
    "message": "Operation completed successfully",
    "data": {...},
    "timestamp": "2025-01-27 10:30:00"
}
```

### Error Response
```json
{
    "status": false,
    "message": "Error message",
    "errors": {...},
    "timestamp": "2025-01-27 10:30:00"
}
```

## 🚨 Error Handling

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Internal Server Error

### Common Error Messages
- Invalid JSON data
- Missing required fields
- Authentication failed
- Resource not found
- Validation errors

## 🔧 Development

### Adding New Endpoints
1. Create new PHP file in appropriate directory
2. Include required helpers and config
3. Use ResponseHelper for standardized responses
4. Add proper error handling
5. Update this documentation

### Testing
- Use Postman or similar API testing tool
- Test all HTTP methods
- Verify error handling
- Check response format consistency

## 📝 Notes
- All endpoints return JSON responses
- Authentication required for protected endpoints
- File uploads limited to 5MB
- Supported file types: PDF, DOC, DOCX, JPG, JPEG, PNG
- Pagination available for list endpoints
- Soft deletes implemented for data integrity

## 🆘 Support
For technical support or questions, please refer to the main project documentation or contact the development team.
