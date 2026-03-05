-- First, remove the old table to ensure a clean start
DROP TABLE IF EXISTS deleted_members;

-- Create the table synchronized with your 40-column PHP logic
CREATE TABLE deleted_members (
    -- Auto-incrementing ID for the archive table
    deleted_id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Audit Tracking (PHP binds 'deletedBy' manually)
    deletedBy VARCHAR(100),
    deletionDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Core Member Data (Matches the 40 columns in system.php)
    memberNo VARCHAR(50),
    memberName VARCHAR(255),
    memberType VARCHAR(50),
    memberClass VARCHAR(50),
    admissionDate DATE,
    siteNo VARCHAR(50),
    address TEXT,
    contactNumber1 VARCHAR(100),
    contactNumber2 VARCHAR(100),
    lanlineNumber VARCHAR(100),
    email VARCHAR(150),
    shareNo VARCHAR(50),
    noOfShares INT,
    ShareCapitalAmount DECIMAL(12,2),
    gender VARCHAR(20),
    caste VARCHAR(50),
    aadhaarNumber VARCHAR(20),
    voterId VARCHAR(50),
    panNumber VARCHAR(20),
    admissionFeePaid VARCHAR(10),
    nomineeName VARCHAR(255),
    relationship VARCHAR(100),
    totalAmount DECIMAL(12,2),
    paidAmount DECIMAL(12,2),
    remainingAmount VARCHAR(50),
    modeOfPayment VARCHAR(50),
    signature VARCHAR(225),
    areaInSqft DECIMAL(12,2),
    areaInCents DECIMAL(12,2),
    
    -- Company & Employment Details
    nameOfCompany VARCHAR(255),
    powerAvailable VARCHAR(100),
    employeesMen INT,
    employeesWomen INT,
    totalEmployees INT,
    dateOfStarting VARCHAR(100),
    
    -- Patta & Document Details
    pattaNo VARCHAR(50),
    pattaType VARCHAR(50),
    village VARCHAR(100),
    documentNo VARCHAR(100),
    profilePicPath VARCHAR(255)
);