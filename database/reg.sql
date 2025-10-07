CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(15),
    dob DATE,
    gender VARCHAR(10),
    blood_group VARCHAR(5),
    password VARCHAR(255),
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);