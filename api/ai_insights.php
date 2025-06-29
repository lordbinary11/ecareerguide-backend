<?php

// CORS headers - Essential for frontend communication
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE'); // Add other methods as needed for your API
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error reporting for debugging (SET TO 0 FOR PRODUCTION!)
error_reporting(E_ALL);
ini_set('display_errors', 1); // <--- Set to 1 for debugging, 0 for production

// Include database connection and JWT helper
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/jwt_helper.php'; // This file should contain generate_jwt and validate_jwt

// --- JWT Authentication Check ---
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? null;

// Fallback for Authorization header if getallheaders() doesn't include it (common for Apache/Nginx)
if (!$authHeader) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
}
if (!$authHeader) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
}

if (!$authHeader) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization token not provided."]);
    exit();
}

// Extract token from "Bearer <token>" string
list($type, $token) = explode(' ', $authHeader);
if (strtolower($type) !== 'bearer' || !$token) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid Authorization header format."]);
    exit();
}

try {
    $decoded = validate_jwt($token); // This function should decode and verify the JWT

    // Check if token is valid and contains necessary information
    if (!$decoded || !isset($decoded->id) || !isset($decoded->email) || !isset($decoded->role)) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or malformed token."]);
        exit();
    }

    $user_id = $decoded->id;
    $user_email = $decoded->email;
    $user_role = $decoded->role; // 'user' or 'counselor'

    // You might want to restrict this endpoint to 'user' role
    if ($user_role !== 'user') {
        http_response_code(403); // Forbidden
        echo json_encode(["error" => "Access denied. Only 'user' role can request AI insights."]);
        exit();
    }

} catch (Exception $e) {
    // Log the actual exception message for debugging, but don't show to user in production
    error_log("JWT Validation Error: " . $e->getMessage());
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized: " . $e->getMessage()]);
    exit();
}

// --- End JWT Authentication Check ---

// Now that the user is authenticated and authorized, proceed with AI insights logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Start AI Insights Generation Logic ---
    $insights = [];

    try {
        // Fetch user's skills from the 'user_skills' table
        $stmt_skills = $pdo->prepare("SELECT skill_name, proficiency FROM user_skills WHERE user_id = ?");
        $stmt_skills->execute([$user_id]);
        $skills = $stmt_skills->fetchAll(PDO::FETCH_ASSOC);

        // Fetch user's interests from the 'user_interests' table
        $stmt_interests = $pdo->prepare("SELECT interest_name FROM user_interests WHERE user_id = ?");
        $stmt_interests->execute([$user_id]);
        $interests = $stmt_interests->fetchAll(PDO::FETCH_ASSOC);

        // Fetch user's experience from the 'user_experience' table
        $stmt_experience = $pdo->prepare("SELECT title, company FROM user_experience WHERE user_id = ?");
        $stmt_experience->execute([$user_id]);
        $experience = $stmt_experience->fetchAll(PDO::FETCH_ASSOC);

        // --- Insight Generation Logic (Conditional based on data presence) ---

        if (!empty($skills)) {
            $skillNames = array_column($skills, 'skill_name');
            $insights[] = [
                "title" => "Skill Analysis",
                "description" => "Based on your skills (" . implode(', ', $skillNames) . "), you have a strong foundation in practical areas. Consider roles requiring these core competencies."
            ];
        } else {
            $insights[] = [
                "title" => "Skill Gap Identified",
                "description" => "You haven't listed any skills. Adding your skills will help us provide more tailored career insights and recommendations."
            ];
        }

        if (!empty($interests)) {
            $interestNames = array_column($interests, 'interest_name');
            $insights[] = [
                "title" => "Interest Alignment",
                "description" => "Your interests (" . implode(', ', $interestNames) . ") suggest a passion for specific fields. Explore career paths that align with these personal pursuits."
            ];
        } else {
            $insights[] = [
                "title" => "Explore Your Interests",
                "description" => "Sharing your interests can help us connect you with relevant resources and opportunities. What are you passionate about?"
            ];
        }

        // Handle user_experience: not a necessity
        if (!empty($experience)) {
            $experienceTitles = array_column($experience, 'title');
            $insights[] = [
                "title" => "Experience Insights",
                "description" => "Your experience in areas like " . implode(', ', $experienceTitles) . " demonstrates practical application of your abilities. Leverage these experiences in your career search."
            ];
        } else {
            // Provide a gentle suggestion for students or those without experience
            $insights[] = [
                "title" => "Building Experience",
                "description" => "While you haven't listed professional experience, remember that internships, volunteer work, and projects are excellent ways to build your profile and gain valuable skills."
            ];
        }

        // Add a generic motivational insight
        $insights[] = [
            "title" => "Growth Mindset",
            "description" => "Your commitment to exploring your profile is a positive step towards personal and professional growth. Keep learning and adapting!"
        ];

        // --- End AI Insights Generation Logic ---

        // Store generated insights in the database
        // First, delete existing insights for this user from 'user_ai_insights'
        $stmt_delete = $pdo->prepare("DELETE FROM user_ai_insights WHERE user_id = ?");
        $stmt_delete->execute([$user_id]);

        // Then, insert new insights into 'user_ai_insights'
        $stmt_insert = $pdo->prepare("INSERT INTO user_ai_insights (user_id, insight_text, generated_at) VALUES (?, ?, NOW())");
        foreach ($insights as $insight) {
            // Combine title and description into one 'insight_text' for your table structure
            $full_insight_text = $insight['title'] . ": " . $insight['description'];
            $stmt_insert->execute([$user_id, $full_insight_text]);
        }

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "AI insights generated and updated successfully.",
            "insights" => $insights // Send insights back to the frontend (frontend still expects title/description)
        ]);

    } catch (PDOException $e) {
        // Database error
        error_log("Database error fetching data or saving AI insights: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to generate AI insights due to a database error.",
            "error" => $e->getMessage() // This will display the actual PDO error
        ]);
    } catch (Exception $e) {
        // General error during insight generation
        error_log("Error generating AI insights: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "An unexpected error occurred during AI insights generation.",
            "error" => $e->getMessage()
        ]);
    }

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Logic to retrieve existing AI insights for the authenticated user

    try {
        // Select from 'user_ai_insights' and map 'insight_text' to 'description' for frontend consistency
        $stmt = $pdo->prepare("SELECT insight_text FROM user_ai_insights WHERE user_id = ? ORDER BY generated_at DESC");
        $stmt->execute([$user_id]);
        $db_insights = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transform insights from DB structure to what frontend expects (e.g., add a dummy title if needed)
        $formatted_insights = [];
        foreach ($db_insights as $db_insight) {
            // A simple way to re-introduce 'title' if your frontend expects it
            // You might need a more sophisticated parsing if the 'insight_text' needs splitting
            $formatted_insights[] = [
                "title" => "Insight", // Generic title, or parse from insight_text if possible
                "description" => $db_insight['insight_text']
            ];
        }

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "AI insights retrieved successfully.",
            "insights" => $formatted_insights
        ]);

    } catch (PDOException $e) {
        error_log("Database error retrieving AI insights: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to retrieve AI insights due to a database error.",
            "error" => $e->getMessage()
        ]);
    }

} else {
    // Method Not Allowed
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed."]);
}

?>