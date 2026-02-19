create database voting_system;

create table admin( 
    id int auto_increment primary key, 
    username varchar(30) not null, 
    contact varchar(30) not null, 
    email varchar(30) not null, 
    password varchar(100) not null, 
    role varchar(30) default "admin", 
    location varchar(30) default "Douala, Cameroon" 
);

INSERT INTO `admin` 
(`id`, `username`, `contact`, `email`, `password`, `role`, `location`) 
VALUES 
(NULL, 'kemzy', '+237 653 426 838', 'support@votesecure.com', '$2y$10$Upms3ODjIU3byjC1sBm0lei22PO/WY78rp.2zmX6n67VkMeOi7S6y', 'admin', 'Douala, Cameroon');


CREATE TABLE voters (
    id int auto_increment PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    dob DATE not null,
    contact VARCHAR(20) not null,
    address TEXT not null,
    status ENUM('pending', 'verified', 'suspended') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    password varchar(100) not null
);

CREATE TABLE elections (
    id int AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    status ENUM('draft', 'upcoming', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
);

CREATE TABLE candidates (
    id int auto_increment PRIMARY KEY,
    voter_id int NOT NULL,
    election_id int NOT NULL,
    party_affiliation VARCHAR(100),
    biography TEXT,
    campaign_statement TEXT,
    profile_image VARCHAR(255),
    status ENUM('pending', 'approved', 'disqualified', 'withdrawn') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id) REFERENCES voters(id),
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
);

CREATE TABLE votes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    voter_id int NOT NULL,
    election_id int NOT NULL,
    candidate_id int NOT NULL,
    vote_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'verified', 'invalid', 'rejected') DEFAULT 'pending',
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (voter_id) REFERENCES voters(id),
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
);