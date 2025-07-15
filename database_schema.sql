-- ECareerGuide Database Schema (PostgreSQL)
-- This file contains all the necessary tables for the ECareerGuide backend application

-- Create database if it doesn't exist (PostgreSQL doesn't have CREATE DATABASE IF NOT EXISTS)
-- You'll need to create the database manually in Render dashboard
-- Database name: ecareer_guidance

-- Users table (for students/regular users)
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    bio TEXT,
    role VARCHAR(10) DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Counselors table
CREATE TABLE IF NOT EXISTS counselors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    specialization VARCHAR(255) NOT NULL,
    experience INTEGER NOT NULL,
    availability TEXT NOT NULL,
    image VARCHAR(500),
    rating DECIMAL(3,2) DEFAULT 0.00,
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Education table
CREATE TABLE IF NOT EXISTS user_education (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    degree VARCHAR(255) NOT NULL,
    institution VARCHAR(255) NOT NULL,
    start_year INTEGER NOT NULL,
    end_year INTEGER,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User Experience table
CREATE TABLE IF NOT EXISTS user_experience (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    company VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User Skills table
CREATE TABLE IF NOT EXISTS user_skills (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    skill_name VARCHAR(255) NOT NULL,
    proficiency VARCHAR(20) DEFAULT 'beginner' CHECK (proficiency IN ('beginner', 'intermediate', 'advanced', 'expert')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (user_id, skill_name)
);

-- User Interests table
CREATE TABLE IF NOT EXISTS user_interests (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    interest_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (user_id, interest_name)
);

-- User Learning Journey table
CREATE TABLE IF NOT EXISTS user_learning_journey (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    platform VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE,
    progress INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'not_started' CHECK (status IN ('not_started', 'in_progress', 'completed', 'paused')),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Resumes table
CREATE TABLE IF NOT EXISTS resumes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    resume_name VARCHAR(255) NOT NULL,
    personal_info JSONB,
    summary_objective TEXT,
    education_json JSONB,
    experience_json JSONB,
    skills_json JSONB,
    projects_json JSONB,
    awards_json JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Scheduled Meetings table
CREATE TABLE IF NOT EXISTS scheduled_meetings (
    id SERIAL PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL,
    counselor_email VARCHAR(255) NOT NULL,
    schedule_date DATE NOT NULL,
    schedule_time TIME NOT NULL,
    purpose TEXT NOT NULL,
    meeting_link VARCHAR(500),
    is_virtual_meet BOOLEAN DEFAULT FALSE,
    meeting_platform VARCHAR(50),
    status VARCHAR(20) DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'completed', 'cancelled', 'rescheduled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    counselor_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(10) DEFAULT 'sent' CHECK (status IN ('sent', 'delivered', 'read')),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES counselors(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    sender_id INTEGER,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    related_id INTEGER,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User AI Insights table
CREATE TABLE IF NOT EXISTS user_ai_insights (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    insight_text TEXT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login Logs table (for users)
CREATE TABLE IF NOT EXISTS login_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Counselor Login Logs table
CREATE TABLE IF NOT EXISTS counselor_login_logs (
    id SERIAL PRIMARY KEY,
    counselor_id INTEGER NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (counselor_id) REFERENCES counselors(id) ON DELETE CASCADE
);

-- Student Activity table
CREATE TABLE IF NOT EXISTS student_activity (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    activity_type VARCHAR(100) NOT NULL,
    activity_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_counselors_email ON counselors(email);
CREATE INDEX idx_scheduled_meetings_user_email ON scheduled_meetings(user_email);
CREATE INDEX idx_scheduled_meetings_counselor_email ON scheduled_meetings(counselor_email);
CREATE INDEX idx_messages_user_counselor ON messages(user_id, counselor_id);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_user_skills_user_id ON user_skills(user_id);
CREATE INDEX idx_user_experience_user_id ON user_experience(user_id);
CREATE INDEX idx_user_education_user_id ON user_education(user_id);
CREATE INDEX idx_user_interests_user_id ON user_interests(user_id);
CREATE INDEX idx_user_learning_journey_user_id ON user_learning_journey(user_id);
CREATE INDEX idx_resumes_user_id ON resumes(user_id);
CREATE INDEX idx_user_ai_insights_user_id ON user_ai_insights(user_id);

-- Create function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers to automatically update updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_counselors_updated_at BEFORE UPDATE ON counselors FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_user_skills_updated_at BEFORE UPDATE ON user_skills FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_user_learning_journey_updated_at BEFORE UPDATE ON user_learning_journey FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_resumes_updated_at BEFORE UPDATE ON resumes FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_scheduled_meetings_updated_at BEFORE UPDATE ON scheduled_meetings FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert sample data (optional)
-- You can uncomment these lines if you want to add sample data

/*
-- Sample counselor (password is 'password' hashed with bcrypt)
INSERT INTO counselors (name, email, password, phone, specialization, experience, availability, bio) VALUES 
('Dr. Sarah Johnson', 'sarah.johnson@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1234567890', 'Career Development', 5, 'Monday-Friday 9AM-5PM', 'Experienced career counselor specializing in tech industry transitions.');

-- Sample user (password is 'password' hashed with bcrypt)
INSERT INTO users (full_name, email, password, phone, bio, role) VALUES 
('John Doe', 'john.doe@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1234567891', 'Software developer looking to transition to product management.', 'user');
*/ 