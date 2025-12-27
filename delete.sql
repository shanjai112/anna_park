-- 1. CREATE THE DELETED_MEMBERS TABLE
-- This table mirrors the structure of the 'members' table to archive deleted records.
CREATE TABLE deleted_members (
    deleted_id INT AUTO_INCREMENT PRIMARY KEY,
    memberNo INT,
    memberName VARCHAR(255) NOT NULL,
    memberType VARCHAR(50) NOT NULL,
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
    familyCard VARCHAR(50),
    voterId VARCHAR(50),
    panNumber VARCHAR(20),
    admissionFeePaid DECIMAL(12,2),
    nomineeName VARCHAR(255),
    relationship VARCHAR(100),
    totalAmount DECIMAL(12,2),
    paidAmount DECIMAL(12,2),
    remainingAmount VARCHAR(50),
    modeOfPayment VARCHAR(50),
    signature VARCHAR(225),
    areaInSqft DECIMAL(12,2),
    areaInCents DECIMAL(12,2),
    linearNorthToSouthWest DECIMAL(10,2),
    linearEastToWestNorth DECIMAL(10,2),
    linearEastToWestSouth DECIMAL(10,2),
    linearNorthToSouthEast DECIMAL(10,2),
    boundaryEast VARCHAR(255),
    boundaryWest VARCHAR(255),
    boundaryNorth VARCHAR(255),
    boundarySouth VARCHAR(255),
    pattaNo VARCHAR(50),
    pattaType VARCHAR(50),
    village VARCHAR(100),
    documentNo VARCHAR(100),
    deletedBy VARCHAR(100),
    deletionDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. ADD PRIMARY KEY AND UNIQUE INDEX to members table for better data integrity
-- Assuming memberNo is the unique identifier (as per your initial table design).
-- NOTE: If your 'members' table already has data, you might need to clean up duplicates
-- before running this ADD CONSTRAINT command.
ALTER TABLE members
ADD PRIMARY KEY (memberNo);