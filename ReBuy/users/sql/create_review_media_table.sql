-- Create review_media table to store multiple photos and videos per review
CREATE TABLE IF NOT EXISTS review_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    media_url VARCHAR(255) NOT NULL,
    media_type ENUM('image', 'video') NOT NULL DEFAULT 'image',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
);

-- Add index for faster queries
CREATE INDEX idx_review_media_review_id ON review_media(review_id);
