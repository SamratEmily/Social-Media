# Social Media Feed Project

This project is a full-stack application developed as a selection task for the Full Stack Engineer position at Appifylab. It features a social media feed with functionalities for creating posts, interacting with them (likes, comments), and managing user privacy.

## Project Structure ( Laravel with react )

The project is organized into two main parts:

- **Root Directory**: Contains the Frontend application built with React, TypeScript, and Vite.
- **`/backend`**: Contains the Backend API built with the Laravel PHP Framework.

## Technologies Used

### Frontend
- **React** (v18)
- **TypeScript**
- **Vite**
- **CSS** (Custom styling)

### Backend
- **Laravel** (PHP Framework)
- **MySQL** (Database)

## Prerequisites

Before you begin, ensure you have the following installed on your machine:

- **Node.js** (v16 or higher) & **npm**
- **PHP** (v8.1 or higher)
- **Composer**
- **MySQL**

## Installation & Setup

Follow these steps to set up the project locally.

### 1. Backend Setup

Navigate to the backend directory and install dependencies:

```bash
cd backend
composer install
```

Copy the environment file and generate the application key:

```bash
cp .env.example .env
php artisan key:generate
```

**Database Configuration:**
1. Create a new MySQL database (e.g., `social_feed`).
2. Open the `.env` file in the `backend` directory.
3. Update the database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=social_feed
DB_USERNAME=your_username(root)
DB_PASSWORD=your_password(root)
```

Run database migrations to create the necessary tables:

```bash
php artisan migrate
```

Start the backend server:

```bash
php artisan serve
```
The backend API will be available at `http://localhost:8000`.

### 2. Frontend Setup

Open a new terminal window, navigate to the project root directory, and install dependencies:

```bash
# If you are in the backend directory, go back one level
cd ..

npm install
```

Start the frontend development server:

```bash
npm run dev
```
The application will be available at the URL shown in the terminal (usually `http://localhost:5173`).

## Features

- **User Authentication**: Login and Registration pages.
- **Social Feed**: View posts from users.
- **Create Post**:
  - Support for text and image uploads.
  - Privacy settings (Public/Private).
- **Interactions**:
  - Like posts.
  - Comment on posts.
  - Reply to comments.

## Notes

- Ensure both the backend (`php artisan serve`) and frontend (`npm run dev`) servers are running simultaneously for the application to function correctly.
- The frontend is configured to communicate with the backend API. If you change the backend port, make sure to update the API base URL in the frontend configuration.

## Security Notes

- **Private posts** are visible only to their owner. Every post, comment, reply and like endpoint checks this and returns `404` for other users.
- **Rate limiting**: `/api/login` and `/api/register` allow 5 requests per minute per IP. Authenticated endpoints allow 60 per minute.
- **Uploads**: images must be jpeg/png/jpg/gif, at most 5 MB, and are saved under random server-generated filenames.
- **Token storage**: the JWT is kept in `localStorage` to keep this demo simple. Any XSS on the frontend could read it. For production, issue the token in an `HttpOnly`, `Secure`, `SameSite` cookie instead. The token is removed on logout and on any `401` response.
