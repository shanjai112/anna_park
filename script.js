// Global State
let currentUser = null;
let members = [];
let updateRequests = [];
let filteredMembers = [];
let currentView = 'members';

// --- Configuration for Change Control ---
const ADMIN_APPROVAL_FIELDS = [
    'memberName', 'siteNo', 'totalAmount', 'paidAmount', 'shareNo', 'noOfShares'
];
// --- Configuration End ---


// Utility Functions
function formatCurrency(amount) {
    if (!amount) return '₹0';
    // Updated for specific Indian Rupee formatting
    return parseFloat(amount || 0).toLocaleString('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 0
    });
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    // Updated for specific Indian date formatting
    try {
        return new Date(dateString).toLocaleDateString('en-IN');
    } catch {
        return dateString;
    }
}

function showToast(title, message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    toast.innerHTML = `
        <div class="toast-title">${title}</div>
        <div class="toast-message">${message}</div>
    `;
    toast.classList.add('show');
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300); // Wait for fade-out transition
    }, 3000);
}

// Helper to close a modal (was missing in original code, but used by the event listeners)
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// ==================== SECURITY & SESSION MANAGEMENT (NEW/MODIFIED) ====================

// NEW: Checks if a session exists on the server and loads the dashboard if true.
async function checkSession() {
    try {
        const res = await fetch("system.php?action=sessionCheck", { credentials: "same-origin" });
        const result = await res.json();
        if (result.loggedIn) {
            currentUser = result.role;
            // Store username locally for client-side use (e.g., deletedBy tracking)
            localStorage.setItem('username', result.username);
            document.getElementById('login-page').style.display = 'none';
            document.getElementById('dashboard-page').style.display = 'block';
            initDashboard(); // Loads data and sets up UI
        } else {
            document.getElementById('login-page').style.display = 'block';
            document.getElementById('dashboard-page').style.display = 'none';
        }
    } catch (err) {
        document.getElementById('login-page').style.display = 'block';
    }
}

// MODIFIED: Handles the login process by sending credentials to the server.
async function handleLogin() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();

    if (!username || !password) {
        showToast('Login Failed', 'Please enter both username and password', 'error');
        return;
    }

    try {
        const formData = new FormData();
        formData.append("username", username);
        formData.append("password", password);

        const res = await fetch("system.php?action=login", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        });

        const result = await res.json();

        if (result.success) {
            // Role is now received from the server
            currentUser = result.role;
            localStorage.setItem('username', result.username); // Store logged-in username
            document.getElementById('login-page').style.display = 'none';
            document.getElementById('dashboard-page').style.display = 'block';
            initDashboard();
            showToast('Login Successful', `Welcome, ${result.username} (${result.role})!`);
        } else {
            showToast('Login Failed', result.error || 'Invalid credentials', 'error');
        }
    } catch (err) {
        showToast('Login Error', 'Unable to connect to server', 'error');
    }
}

// MODIFIED: Destroys the server session before clearing local state.
function handleLogout() {
    fetch("system.php?action=logout", { method: "GET", credentials: "same-origin" })
        .finally(() => {
            currentUser = null;
            localStorage.removeItem('username'); // Clear local username storage
            document.getElementById('dashboard-page').style.display = 'none';
            document.getElementById('login-page').style.display = 'block';

            document.getElementById('username').value = '';
            document.getElementById('password').value = '';

            showToast('Logged Out', 'You have been logged out successfully');
        });
}

// ==================== BACKEND CONNECT (MODIFIED for security checks) ====================
async function loadData() {
    try {
        const resMembers = await fetch("system.php?action=getMembers");

        // ADDED: Check for 401 Unauthorized response
        if (resMembers.status === 401) {
            showToast('Authentication Error', 'Session expired. Logging out.', 'error');
            handleLogout();
            return;
        }

        members = await resMembers.json();

        const resRequests = await fetch("system.php?action=getRequests");

        // Only load requests if the status is not 401
        if (resRequests.status !== 401) {
            updateRequests = await resRequests.json();
        }

        filteredMembers = [...members];
        renderMembers();
        updateRequestsCount();
    } catch (err) {
        showToast("Error", "Failed to load data from server", "error");
    }
}

// NEW FUNCTION: Fetch and display the overall financial summary
async function loadFinancialSummary() {
    try {
        const res = await fetch("system.php?action=getFinancialSummary");

        if (res.status === 401) return; // Skip if session expired

        const result = await res.json();

        if (result.success && result.data) {
            const data = result.data;
            document.getElementById('summary-commitment').textContent = formatCurrency(data.totalCommitment);
            document.getElementById('summary-paid').textContent = formatCurrency(data.totalPaid);
            document.getElementById('summary-remaining').textContent = formatCurrency(data.totalRemaining);
        } else {
            // Handle error or empty data by showing zeros
            document.getElementById('summary-commitment').textContent = formatCurrency(0);
            document.getElementById('summary-paid').textContent = formatCurrency(0);
            document.getElementById('summary-remaining').textContent = formatCurrency(0);
        }
    } catch (err) {
        console.error("Failed to load financial summary:", err);
        showToast("Error", "Failed to load financial summary", "error");
    }
}


// Original addMember implementation
async function addMember(memberData) {
    try {
        const formData = new FormData();
        for (const key in memberData) {
            formData.append('new-' + key, memberData[key]);
        }
        const res = await fetch("system.php?action=addMember", {
            method: "POST",
            body: formData
        });

        if (res.status === 401 || res.status === 403) {
            showToast('Permission Denied', 'You do not have permission to add members.', 'error');
            return;
        }

        const result = await res.json();
        if (result.success) {
            await loadData();
            showToast('Member Added', `Member ${memberData.memberName} has been added successfully.`);
        } else {
            console.error("Backend Error:", result);

            // 🔴 SEND ERROR TO SERVER FOR LOGGING
            fetch("log_error.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    source: "ADD_MEMBER",
                    error: result.error || "Failed to add member"
                })
            });

            showToast(
                "Error",
                result.error ? result.error : "Failed to add member",
                "error"
            );
        }

    } catch (err) {
        showToast("Error", "Failed to add member", "error");
    }
}

// NEW FUNCTION: Handles the file selection and upload to the server
window.handlePhotoUpload = async function (memberNo) {
    const fileInput = document.getElementById('photo-upload-input');
    if (!fileInput.files || fileInput.files.length === 0) {
        return;
    }

    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('memberNo', memberNo);
    formData.append('profilePic', file);

    showToast('Uploading Photo', 'Please wait...', 'warning');

    try {
        const res = await fetch("system.php?action=uploadPhoto", {
            method: "POST",
            body: formData
        });

        if (res.status === 401 || res.status === 403) {
            showToast('Permission Denied', 'You do not have permission to upload photos.', 'error');
            return;
        }

        const result = await res.json();

        if (result.success) {
            showToast('Upload Complete', 'Profile picture saved successfully.', 'success');

            // Reload data and redisplay modal to show new picture
            await loadData();
            viewMemberDetails(memberNo); // Re-open the modal
        } else {
            showToast('Upload Failed', result.error || 'Failed to upload photo.', 'error');
        }
    } catch (err) {
        showToast('Upload Error', 'Failed to connect to the upload server.', 'error');
    }
}


async function submitUpdateRequest(requestData) {
    try {
        const res = await fetch("system.php?action=addRequest", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(requestData)
        });

        if (res.status === 401) {
            showToast('Authentication Error', 'Session expired. Logging out.', 'error');
            handleLogout();
            return;
        }

        const result = await res.json();
        if (result.success) {
            await loadData();
            showToast('Update Request Sent', 'Your update request has been sent to the administrator.');
        } else {
            showToast("Error", result.error || "Failed to submit request", "error");
        }
    } catch {
        showToast("Error", "Failed to submit request", "error");
    }
}

// NEW: Direct update function for non-critical fields
async function directUpdateMember(memberNo, field, newValue) {
    try {
        const res = await fetch("system.php?action=directUpdateMember", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ memberNo, field, newValue })
        });

        if (res.status === 401 || res.status === 403) {
            showToast('Permission Denied', 'You do not have permission to perform this update.', 'error');
            return false;
        }

        const result = await res.json();
        if (result.success) {
            await loadData();
            showToast('Update Successful', `The field ${field} has been updated directly.`);
            return true;
        } else {
            showToast("Error", result.error || "Failed to perform direct update", "error");
            return false;
        }
    } catch {
        showToast("Error", "Failed to perform direct update", "error");
        return false;
    }
}

async function approveRequest(id) {
    const request = updateRequests.find(r => r.id == id);
    if (!request) return;

    try {
        const res = await fetch("system.php?action=updateRequestStatus", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id,
                memberNo: request.memberNo,
                field: request.field,
                newValue: request.newValue,
                status: "approved"
            })
        });

        if (res.status === 401 || res.status === 403) {
            showToast('Permission Denied', 'You do not have permission to approve requests.', 'error');
            return;
        }

        const result = await res.json();
        if (result.success) {
            await loadData();
            // IMPORTANT: Reload the summary after approval, as it might affect overall totals
            await loadFinancialSummary();
            showToast('Request Approved', `Update request for ${request.memberName} has been approved.`);
        } else {
            showToast("Error", result.error || "Failed to approve request", "error");
        }
    } catch {
        showToast("Error", "Failed to approve request", "error");
    }
}

async function rejectRequest(id) {
    try {
        const res = await fetch("system.php?action=updateRequestStatus", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id, status: "rejected" })
        });

        if (res.status === 401 || res.status === 403) {
            showToast('Permission Denied', 'You do not have permission to reject requests.', 'error');
            return;
        }

        await loadData();
        showToast('Request Rejected', 'Update request has been rejected.', 'error');
    } catch {
        showToast("Error", "Failed to reject request", "error");
    }
}

// MODIFIED: Handles the request to delete a member (requires Admin approval)
window.requestDeleteMember = function (memberNo) {
    const member = members.find(m => m.memberNo === memberNo);
    if (!member) return;

    // Use a custom modal replacement for confirmation
    const confirmDelete = document.createElement('div');
    confirmDelete.className = 'modal-confirmation';
    confirmDelete.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Request Member Deletion</h2>
                <button onclick="document.body.removeChild(this.closest('.modal-confirmation'))" class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to request the deletion of **${member.memberName} (Member No: ${member.memberNo})**?</p>
                <p style="color: var(--destructive); font-weight: 600;">This action requires Administrator approval.</p>
                <textarea id="delete-reason" rows="3" placeholder="Please provide a reason for the deletion request (Mandatory)..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn-outline" onclick="document.body.removeChild(this.closest('.modal-confirmation'))">Cancel</button>
                <button class="btn-danger" id="confirm-delete-btn">Submit Deletion Request</button>
            </div>
        </div>
    `;
    document.body.appendChild(confirmDelete);

    document.getElementById('confirm-delete-btn').onclick = async function () {
        const reason = document.getElementById('delete-reason').value.trim();
        if (!reason) {
            showToast('Missing Reason', 'A reason is required to submit a deletion request.', 'error');
            return;
        }

        const requestData = {
            memberNo: member.memberNo,
            memberName: member.memberName,
            field: 'DELETE_MEMBER', // Special field name to flag deletion
            currentValue: member.memberName,
            newValue: 'DELETE_REQUESTED',
            reason: reason
        };

        const res = await fetch("system.php?action=addRequest", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(requestData)
        });

        // FIX: Close the modal immediately after API call to clear UI
        document.body.removeChild(confirmDelete.closest('.modal-confirmation'));

        if (res.status === 200) {
            const result = await res.json();
            if (result.success) {
                await loadData();
                showToast('Deletion Request Sent', `Deletion request for ${member.memberNo} submitted for admin approval.`);
            } else {
                showToast("Error", result.error || "Failed to submit deletion request", "error");
            }
        } else {
            showToast("Error", "Failed to communicate with server. Check network or server logs.", "error");
        }
    };


    // Add necessary styling for the temporary modal
    const style = document.createElement('style');
    style.innerHTML = `
        .modal-confirmation {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
            background-color: rgba(0, 0, 0, 0.6); display: flex; 
            align-items: center; justify-content: center; z-index: 9999;
        }
        .modal-confirmation .modal-content {
            max-width: 450px; padding: 20px;
        }
        .modal-confirmation textarea {
            width: 100%; margin-top: 10px; border: 1px solid var(--gov-border); border-radius: 6px; padding: 10px;
        }
    `;
    document.head.appendChild(style);
};

// ==================== LOGIN INIT (MODIFIED) ====================
function initLogin() {
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const userLoginBtn = document.getElementById('user-login');
    const adminLoginBtn = document.getElementById('admin-login');

    function updateLoginButtons() {
        const hasCredentials = usernameInput.value.trim() && passwordInput.value.trim();
        userLoginBtn.disabled = !hasCredentials;
        adminLoginBtn.disabled = !hasCredentials;
    }

    usernameInput.addEventListener('input', updateLoginButtons);
    passwordInput.addEventListener('input', updateLoginButtons);

    // MODIFIED: Both buttons call the same handleLogin, as the server determines the role.
    userLoginBtn.addEventListener('click', handleLogin);
    adminLoginBtn.addEventListener('click', handleLogin);

    passwordInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !userLoginBtn.disabled) {
            handleLogin();
        }
    });
}


// ==================== DASHBOARD (Original functionality preserved) ====================
async function initDashboard() {
    const userRoleDisplay = document.getElementById('user-role-display');
    const addMemberBtn = document.getElementById('add-member-btn');
    const requestsTab = document.getElementById('requests-tab');
    const membersTab = document.getElementById('members-tab');
    const logoutBtn = document.getElementById('logout-btn');

    userRoleDisplay.textContent = `Logged in as: ${currentUser.toUpperCase()}`;

    // Note: The script.js assumes roles 'head1' and 'administrator' based on previous logic.
    if (currentUser === 'head1') {
        addMemberBtn.style.display = 'flex';
        requestsTab.style.display = 'none';
    } else if (currentUser === 'administrator') {
        addMemberBtn.style.display = 'none';
        requestsTab.style.display = 'flex';
    }

    membersTab.addEventListener('click', () => switchView('members'));
    requestsTab.addEventListener('click', () => switchView('requests'));
    logoutBtn.addEventListener('click', handleLogout);
    addMemberBtn.addEventListener('click', () => openModal('add-member-modal'));

    initSearch();

    // ⭐ NEW: Load the Financial Summary first
    await loadFinancialSummary();

    // Existing data load
    await loadData();

    switchView('members');
}

function switchView(view) {
    currentView = view;
    document.getElementById('members-view').style.display = view === 'members' ? 'block' : 'none';
    document.getElementById('requests-view').style.display = view === 'requests' ? 'block' : 'none';
    document.getElementById('members-tab').classList.toggle('active', view === 'members');
    document.getElementById('requests-tab').classList.toggle('active', view === 'requests');
    if (view === 'requests') renderRequests();
}

// ==================== SEARCH (Original functionality preserved) ====================
function initSearch() {
    const searchBtn = document.getElementById('search-btn');
    const clearBtn = document.getElementById('clear-search-btn');
    const searchTerm = document.getElementById('search-term');

    searchBtn.addEventListener('click', performSearch);
    clearBtn.addEventListener('click', clearSearch);
    searchTerm.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performSearch();
    });
}

function performSearch() {
    const searchTerm = document.getElementById('search-term').value.trim();
    const searchBy = document.getElementById('search-by').value;

    if (!searchTerm) {
        filteredMembers = [...members];
    } else {
        filteredMembers = members.filter(member => {
            const field = member[searchBy];
            if (!field) return false;
            return field.toString().trim().toLowerCase() === searchTerm.toLowerCase(); // ✅ exact match
        });
    }

    renderMembers();
}

function clearSearch() {
    document.getElementById('search-term').value = '';
    filteredMembers = [...members];
    renderMembers();
}

// ==================== MEMBERS (MODIFIED: ADDED SITE DETAILS BUTTON) ====================
function renderMembers() {
    const tbody = document.getElementById('members-tbody');
    const countElement = document.getElementById('members-count');
    countElement.textContent = `Member Dataset (${filteredMembers.length} records)`;

    tbody.innerHTML = filteredMembers.map((member, index) => {
        let updateBtn = '';
        if (currentUser == 'head1') {
            updateBtn = `<button class="btn-update small" onclick="showUpdateModal('${member.memberNo}')">Update</button>`;
        }
        return `
            <tr class="${index % 2 === 0 ? 'even' : 'odd'}">
                <td style="font-weight: 600; color: var(--gov-primary);">${member.memberNo}</td>
                <td>${member.memberName}</td>
                <td>
                    <span class="badge ${member.memberType === 'Individual' ? 'badge-default' : 'badge-secondary'}">
                        ${member.memberType}
                    </span>
                </td>
                <td>${member.siteNo}</td>
                <td>${member.contactNumber1}</td>
                <td>${formatCurrency(member.totalAmount)}</td>
                <td style="color: hsl(142, 76%, 36%); font-weight: 500;">${formatCurrency(member.paidAmount)}</td>
                <td style="color: hsl(35, 80%, 50%); font-weight: 500;">${formatCurrency(member.totalAmount - member.paidAmount)}</td>
                <td>${member.modeOfPayment || 'N/A'}</td>
                <td style="white-space: nowrap;">
                    <div style="display: flex; gap: 0.5rem;">
                        <button class="btn-site-details small" onclick="showSiteDetails('${member.memberNo}')">Site Details</button>
                        <button class="btn-extra-info small" onclick="viewMemberDetails('${member.memberNo}')">Extra Info</button>
                        <button class="btn-financial small" onclick="showFinancialDetails('${member.memberNo}')">Financial</button>
                        ${updateBtn}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// ==================== REQUESTS (Original functionality preserved) ====================
function updateRequestsCount() {
    const pendingCount = updateRequests.filter(r => r.status === 'pending').length;
    const countElement = document.getElementById('requests-count');
    countElement.textContent = `Approvals (${pendingCount})`;
}

function renderRequests() {
    const container = document.getElementById('requests-list');
    const titleElement = document.getElementById('requests-title');
    titleElement.textContent = `Update Requests (${updateRequests.length})`;

    if (updateRequests.length === 0) {
        container.innerHTML = `<div class="text-center" style="padding: 2rem; color: var(--muted-foreground);">No update requests found.</div>`;
        return;
    }

    container.innerHTML = updateRequests.map(request => {
        let actionButtons = '';
        let specialMessage = '';

        // Custom handling for DELETE_MEMBER request
        if (request.field === 'DELETE_MEMBER') {
            specialMessage = `<div style="color: var(--destructive); font-weight: 600;">Action: DELETE MEMBER (Requires Admin Approval)</div>`;
        }

        if (currentUser === 'administrator' && request.status === 'pending') {
            actionButtons = `
                <div class="request-actions">
                    <button class="btn-success small" onclick="approveRequest('${request.id}')">Approve</button>
                    <button class="btn-danger small" onclick="rejectRequest('${request.id}')">Reject</button>
                </div>
            `;
        }

        return `
            <div class="request-item">
                <div class="request-header">
                    <div class="request-member-info">
                        <h4>${request.memberName} (#${request.memberNo})</h4>
                        <p>Field: <strong>${request.field}</strong></p>
                    </div>
                    ${getStatusBadgeHtml(request.status)}
                </div>
                ${specialMessage}
                <div class="request-values">
                    <div><div class="value-label">Current Value</div><div class="value-box">${request.currentValue}</div></div>
                    <div><div class="value-label">Requested Value</div><div class="value-box new">${request.newValue}</div></div>
                </div>
                <div class="request-reason"><div class="value-label">Reason</div><p>${request.reason}</p></div>
                <div class="request-footer">
                    <div class="request-meta"><p>Requested by: ${request.requestedBy}</p><p>Date: ${formatDate(request.requestDate)}</p></div>
                    ${actionButtons}
                </div>
            </div>
        `;
    }).join('');
}

function getStatusBadgeHtml(status) {
    const badges = {
        pending: '<span class="badge badge-warning"> Pending</span>',
        approved: '<span class="badge badge-success"> Approved</span>',
        rejected: '<span class="badge badge-danger"> Rejected</span>'
    };
    return badges[status] || `<span class="badge badge-secondary">${status}</span>`;
}

// ==================== MODALS (Original functionality preserved) ====================
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}
// closeModal is defined above as a helper
// Run this ONCE, globally, after the DOM is loaded
document.addEventListener("click", function (e) {
    // Handle Print button clicks
    const printBtn = e.target.closest(".print-btn");
    if (printBtn) {
        const memberNo = printBtn.dataset.memberno;
        window.location.href = `print_receipt.php?memberNo=${memberNo}`;
        return; // Stop propagation
    }

    // Handle Extra Info button clicks (if needed)
    const extraInfoBtn = e.target.closest(".btn-extra-info");
    if (extraInfoBtn) {
        const memberNo = extraInfoBtn.dataset.memberno || extraInfoBtn.getAttribute('onclick').match(/'(\d+)'/)[1];
        viewMemberDetails(memberNo);
        return;
    }

    // Add other buttons similarly
});


// ==================== MEMBER DETAILS (MODIFIED: FINAL PHOTO LOGIC) ====================
window.viewMemberDetails = function (memberNo) {
    const member = members.find(m => m.memberNo === memberNo);
    if (!member) return;
    const modal = document.getElementById('member-details-modal');
    const title = document.getElementById('member-details-title');
    const subtitle = document.getElementById('member-details-subtitle');
    const content = document.getElementById('member-details-content');

    title.textContent = `Member Details - ${member.memberName}`;
    subtitle.textContent = `Member No: ${member.memberNo}`;

    // Helper to format values and handle empty data
    const displayVal = (val) => (val === null || val === undefined || val === '' || val === 0 || val === '0') ? 'Not provided' : val;

    function createInfoCard(title, data) {
        const items = data.map(item => `
            <div class="info-item">
                <div class="info-label">${item.label}:</div>
                <div class="info-value">${displayVal(item.value)}</div>
            </div>
        `).join('');
        return `<div class="info-card"><h3>${title}</h3>${items}</div>`;
    }

    // NEW: Delete button, only for Head1 role
    let deleteButtonHtml = '';
    if (currentUser === 'head1') {
        deleteButtonHtml = `
            <button class="btn-danger large mt-4" onclick="requestDeleteMember('${member.memberNo}')">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                Request Member Deletion
            </button>
        `;
    }

    let printhtml = `
        <button class="btn-danger large mt-4 print-btn" data-memberno="${member.memberNo}">
            Print Member
        </button>
    `;

    // --- PHOTO & UPLOAD LOGIC ---
    const photoPath = member.photo || member.profilePicPath;
    const photoUrl = photoPath ? 'uploads/' + photoPath : null;

    let photoContentHtml = '';
    if (photoUrl) {
        photoContentHtml = `<img src="${photoUrl}" alt="Member Photo" class="member-photo-display">`;
        if (currentUser !== 'administrator') {
            photoContentHtml += `
                <button class="btn-secondary large" onclick="document.getElementById('photo-upload-input').click()">Change Photo</button>
            `;
        }
    } else {
        photoContentHtml = `
            <div class="profile-placeholder">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-circle"><path d="M18 20a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1"/><circle cx="12" cy="10" r="3"/><circle cx="12" cy="12" r="10"/></svg>
            </div>
        `;
        if (currentUser !== 'administrator') {
            photoContentHtml += `
                <button class="btn-primary large" onclick="document.getElementById('photo-upload-input').click()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                    Add Profile Picture
                </button>
            `;
        }
    }

    const memberHeaderAndPhotoHtml = `
        <div class="member-summary">
            <div class="member-info-column">
                <div class="member-avatar">
                    <div class="avatar-circle">${member.memberName.charAt(0)}</div>
                    <div>
                        <h2 class="member-name">${member.memberName}</h2>
                        <div class="member-badges">
                            <span class="badge badge-default">${member.memberType}</span>
                            <span class="badge badge-outline">Site ${member.siteNo}</span>
                            <span class="badge badge-outline">Class ${member.memberClass || 'N/A'}</span>
                        </div>
                    </div>
                </div>
                ${deleteButtonHtml}<br>
                ${printhtml}
            </div>
            <div class="photo-upload-container">${photoContentHtml}</div>
            <input type="file" id="photo-upload-input" accept="image/*" style="display: none;" onchange="handlePhotoUpload('${member.memberNo}')">
        </div>
    `;

    const personalInfo = [
        { label: 'Member No', value: member.memberNo },
        { label: 'Member Name', value: member.memberName },
        { label: 'Member Class', value: member.memberClass },
        { label: 'Member Type', value: member.memberType },
        { label: 'Gender', value: member.gender },
        { label: 'Caste', value: member.caste }
    ];

    const contactInfo = [
        { label: 'Contact Number1', value: member.contactNumber1 },
        { label: 'Contact Number2', value: member.contactNumber2 },
        { label: 'Lanline Number', value: member.lanlineNumber },
        { label: 'E-Mail', value: member.email },
        { label: 'Address', value: member.address },
        { label: 'Site No', value: member.siteNo },
        { label: 'Signature', value: member.signature }
    ];

    const documentInfo = [
        { label: 'Aadhaar', value: member.aadhaarNumber },
        { label: 'Voter ID', value: member.voterId },
        { label: 'PAN', value: member.panNumber }
    ];

    const financialInfo = [
        { label: 'Share No', value: member.shareNo },
        { label: 'No Of Share', value: member.noOfShares },
        { label: 'Share Capital Amount', value: formatCurrency((member.noOfShares || 0) * 500) },
        { label: 'Admission Date', value: formatDate(member.admissionDate) }
    ];

    // UPDATED: Using correct DB column names for Company & Starting Date
    const siteInfo = [
        { label: 'Area in Sqft', value: member.areaInSqft },
        { label: 'Area in Cents', value: member.areaInCents },
        { label: 'Company Name', value: member.nameOfCompany },
        { label: 'Power Available', value: member.powerAvailable },
        { label: 'Starting Date', value: formatDate(member.dateOfStarting) },
        { label: 'Village', value: member.village },
        { label: 'Document No', value: member.documentNo }
    ];

    const infoGrid = `
       <div class="info-grid-container">
            ${createInfoCard('Personal Information', personalInfo)}
            ${createInfoCard('Contact Information', contactInfo)}
            ${createInfoCard('Company & Site Summary', siteInfo)}
            ${createInfoCard('Document Information', documentInfo)}
            ${createInfoCard('Share & Admission Details', financialInfo)}
            ${createInfoCard('Nominee & Status Details', [
        { label: 'Nominee Name', value: member.nomineeName },
        { label: 'Relationship', value: member.relationship },
        { label: 'Current Status', value: member.admissionFeePaid },
        { label: 'Mode of Payment', value: member.modeOfPayment }
    ])}
        </div>
    `;

    content.innerHTML = `
        <div class="member-details-info-grid">
            ${memberHeaderAndPhotoHtml}
            ${infoGrid}
        </div>
    `;
    modal.style.display = 'flex';
};

window.showSiteDetails = function (memberNo) {
    const member = members.find(m => m.memberNo === memberNo);
    if (!member) return;
    const modal = document.getElementById('site-details-modal');
    const title = document.getElementById('site-details-title');
    const subtitle = document.getElementById('site-details-subtitle');
    const content = document.getElementById('site-details-content');

    title.textContent = `Site Details - ${member.siteNo}`;
    subtitle.textContent = `Owner: ${member.memberName} (#${member.memberNo})`;

    function createSiteCard(data) {
        const items = data.map(item => `
            <div class="info-item">
                <div class="info-label">${item.label}:</div>
                <div class="info-value">${item.value || 'N/A'}</div>
            </div>
        `).join('');
        return `<div class="info-card">${items}</div>`;
    }

    const areaDetails = [
        { label: 'Area in Sqft', value: member.areaInSqft },
        { label: 'Area in Cents', value: member.areaInCents }
    ];

    const linearMeasurements = [
        { label: 'Name of Company', value: member.nameOfCompany },
        { label: 'Power Available', value: member.powerAvailable },
        { label: 'Employees Men', value: member.employeesMen },
        { label: 'Employees Women', value: member.employeesWomen }
    ];

    const boundaryDetails = [
        { label: 'Total Employees', value: member.totalEmployees },
        { label: 'Date of Starting', value: member.dateOfStarting }
    ];

    const documentDetails = [
        { label: 'Patta No', value: member.pattaNo },
        { label: 'Patta Type', value: member.pattaType },
        { label: 'Village', value: member.village },
        { label: 'Document No', value: member.documentNo }
    ];


    content.innerHTML = `
        <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
            <div class="info-card"><h3>AREA DETAILS</h3>${createSiteCard(areaDetails)}</div>
            <div class="info-card"><h3>LINEAR MEASUREMENTS</h3>${createSiteCard(linearMeasurements)}</div>
            <div class="info-card"><h3>BOUNDARIES (SF.NO.)</h3>${createSiteCard(boundaryDetails)}</div>
            <div class="info-card"><h3>PATTA & DOCUMENT DETAILS</h3>${createSiteCard(documentDetails)}</div>
        </div>
    `;
    modal.style.display = 'flex';
};

window.showFinancialDetails = function (memberNo) {
    const member = members.find(m => m.memberNo === memberNo);
    if (!member) return;
    const modal = document.getElementById('financial-modal');
    const title = document.getElementById('financial-title');
    const content = document.getElementById('financial-content');

    title.textContent = `Financial Details - ${member.memberName}`;

    const financialData = [
        { label: 'Total Amount', value: formatCurrency(member.totalAmount) },
        { label: 'Paid Amount', value: formatCurrency(member.paidAmount) },
        { label: 'Remaining Amount', value: formatCurrency(member.totalAmount - member.paidAmount) },
        { label: 'Mode of Payment', value: member.modeOfPayment || 'N/A' },
        { label: 'Share Capital Amount', value: formatCurrency((member.noOfShares) * 500) },
        {
            label: 'Admission Fee Paid',
            value: member.admissionFeePaid
        },
        { label: 'Share No', value: member.shareNo || 'N/A' },
        { label: 'No Of Share', value: member.noOfShares }
    ];

    content.innerHTML = `
        <div class="info-grid">
            <div class="info-card">
                <h3>Financial Summary</h3>
                ${financialData.map(item => `
                    <div class="info-item">
                        <div class="info-label">${item.label}:</div>
                        <div class="info-value">${item.value}</div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;

    modal.style.display = 'flex';
};

// MODIFIED: Uses ADMIN_APPROVAL_FIELDS config and handles direct updates vs. requests
window.showUpdateModal = function (memberNo) {
    const member = members.find(m => m.memberNo === memberNo);
    if (!member) return;
    const modal = document.getElementById('update-request-modal');
    const title = document.getElementById('update-request-title');
    const fieldSelect = document.getElementById('update-field');
    const updateForm = document.getElementById('update-request-form');

    title.textContent = `Request Update for Member: ${member.memberName} (${member.memberNo})`;

    const fields = [
        { value: 'memberName', label: 'Member Name', current: member.memberName, required: true },
        { value: 'memberType', label: 'Member Type', current: member.memberType, required: true },
        { value: 'memberClass', label: 'Member Class', current: member.memberClass || 'N/A', required: true },
        { value: 'admissionDate', label: 'Admission Date', current: member.admissionDate || 'N/A', required: false },
        { value: 'siteNo', label: 'Site No', current: member.siteNo, required: true },
        { value: 'address', label: 'Address', current: member.address || 'N/A', required: false },
        { value: 'contactNumber1', label: 'Contact Number 1', current: member.contactNumber1, required: false },
        { value: 'contactNumber2', label: 'Contact Number 2', current: member.contactNumber2 || 'N/A', required: false },
        { value: 'lanlineNumber', label: 'Landline Number', current: member.lanlineNumber || 'N/A', required: false },
        { value: 'email', label: 'Email', current: member.email || 'N/A', required: false },
        { value: 'shareNo', label: 'Share No', current: member.shareNo || 'N/A', required: false },
        { value: 'noOfShares', label: 'No of Shares', current: member.noOfShares || 0, required: true },
        { value: 'gender', label: 'Gender', current: member.gender || 'N/A', required: false },
        { value: 'caste', label: 'Caste', current: member.caste || 'N/A', required: false },
        { value: 'aadhaarNumber', label: 'Aadhaar Number', current: member.aadhaarNumber || 'N/A', required: true },
        { value: 'voterId', label: 'Voter ID', current: member.voterId || 'N/A', required: false },
        { value: 'panNumber', label: 'PAN Number', current: member.panNumber || 'N/A', required: false },
        { value: 'nomineeName', label: 'Nominee Name', current: member.nomineeName || 'N/A', required: false },
        { value: 'relationship', label: 'Relationship', current: member.relationship || 'N/A', required: false },
        { value: 'totalAmount', label: 'Total Amount', current: member.totalAmount || '0.00', required: false },
        { value: 'paidAmount', label: 'Paid Amount', current: member.paidAmount || '0.00', required: false },
        { value: 'modeOfPayment', label: 'Mode of Payment', current: member.modeOfPayment || 'N/A', required: false },
        { value: 'admissionFeePaid', label: 'Member Status', current: member.admissionFeePaid || 'N/A', required: false }, { value: 'signature', label: 'Signature', current: member.signature || 'N/A', required: false },
        // NEW SITE FIELDS ADDED
        { value: 'areaInSqft', label: 'Area in Sqft', current: member.areaInSqft || 'N/A', required: false },
        { value: 'areaInCents', label: 'Area in Cents', current: member.areaInCents || 'N/A', required: false },
        { value: 'nameOfCompany', label: 'Name of Company', current: member.nameOfCompany || 'N/A', required: false },
        { value: 'powerAvailable', label: 'Power Available', current: member.powerAvailable || 'N/A', required: false },
        { value: 'employeesMen', label: 'Employees Men', current: member.employeesMen || 'N/A', required: false },
        { value: 'employeesWomen', label: 'Employees Women', current: member.employeesWomen || 'N/A', required: false },
        { value: 'totalEmployees', label: 'Total Employees', current: member.totalEmployees || 'N/A', required: false },
        { value: 'dateOfStarting', label: 'Date of Starting', current: member.dateOfStarting || 'N/A', required: false },
        // Removed unsupported boundaryNorth/South fields
        // { value: 'boundaryNorth', label: 'Boundary North', current: member.boundaryNorth || 'N/A', required: false },
        // { value: 'boundarySouth', label: 'Boundary South', current: member.boundarySouth || 'N/A', required: false },
        { value: 'pattaNo', label: 'Patta No', current: member.pattaNo || 'N/A', required: false },
        { value: 'pattaType', label: 'Patta Type', current: member.pattaType || 'N/A', required: false },
        { value: 'village', label: 'Village', current: member.village || 'N/A', required: false },
        { value: 'documentNo', label: 'Document No', current: member.documentNo || 'N/A', required: false }
    ];



    fieldSelect.innerHTML = '<option value="">Select field to update</option>' +
        fields.map(field => {
            const needsApproval = ADMIN_APPROVAL_FIELDS.includes(field.value);
            const approvalTag = needsApproval ? ' (Admin Approval Required)' : ' (Direct Update)';
            return `<option value="${field.value}" data-current="${field.current}" data-approval="${needsApproval}">${field.label}${approvalTag}</option>`;
        }).join('');

    // Update the submit button text based on selection
    fieldSelect.onchange = function () {
        // ... existing logic to show current value ...
        document.getElementById('current-value-display').style.display = this.value ? 'block' : 'none';
        const selectedOption = this.selectedOptions[0];
        if (!selectedOption) return;

        const needsApproval = selectedOption.dataset.approval === 'true';
        const submitBtn = updateForm.querySelector('button[type="submit"]');
        const reasonLabel = updateForm.querySelector('label[for="update-reason"]');

        if (needsApproval) {
            submitBtn.textContent = "Send Update Request";
            submitBtn.classList.add('btn-primary');
            submitBtn.classList.remove('btn-success');
            reasonLabel.textContent = "Reason for Update (Mandatory for Admin Approval)";
        } else {
            submitBtn.textContent = "Directly Apply Update";
            submitBtn.classList.add('btn-success');
            submitBtn.classList.remove('btn-primary');
            reasonLabel.textContent = "Reason for Update (Optional for Direct Update)";
        }
    };

    // Trigger change to set initial button state if a field is pre-selected (though usually empty)
    fieldSelect.dispatchEvent(new Event('change'));

    modal.style.display = 'flex';
};

window.approveRequest = approveRequest;
window.rejectRequest = rejectRequest;
window.requestDeleteMember = requestDeleteMember; // Export the new function

// ==================== EVENT LISTENERS (MODIFIED) ====================
document.addEventListener('DOMContentLoaded', function () {

    // MODIFIED: Start with a session check
    checkSession();
    initLogin();

    document.querySelectorAll('.close-btn, .back-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = this.closest('.modal');
            if (modal) modal.style.display = 'none';
        });
    });

    document.querySelectorAll('.cancel-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = this.closest('.modal');
            if (modal) modal.style.display = 'none';
        });
    });

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) this.style.display = 'none';
        });
    });

    document.forms['add-member-form'].addEventListener('submit', async function (e) {
        e.preventDefault();
        const memberData = {};
        this.querySelectorAll('input, select').forEach(input => {
            const fieldName = input.name.replace('new-', '');
            // Special handling for the address fields, we don't need 'address'
            if (fieldName.startsWith('address-')) {
                memberData[fieldName] = input.value;
            } else if (fieldName !== 'address') {
                memberData[fieldName] = input.value;
            }
        });

        // --- Call addMember and wait for result ---
        const form = this;
        try {
            const formData = new FormData();
            for (const key in memberData) {
                formData.append('new-' + key, memberData[key]);
            }
            const res = await fetch("system.php?action=addMember", {
                method: "POST",
                body: formData
            });

            // ADDED: Check for permission denial
            if (res.status === 401 || res.status === 403) {
                showToast('Permission Denied', 'You do not have permission to add members.', 'error');
                closeModal('add-member-modal');
                return;
            }

            const result = await res.json();
            if (result.success) {
                await loadData();
                // ⭐ NEW: Reload the summary after adding a member
                await loadFinancialSummary();
                showToast('Member Added', `Member ${memberData.memberName} has been added successfully.`);
                closeModal('add-member-modal');
                form.reset();
            } else {
                showToast("Error", result.error || "Failed to add member", "error");
            }
        } catch (err) {
            showToast("Error", "Failed to add member", "error");
        }
    });

    // MODIFIED: This listener now branches into two flows: direct update or approval request.
    document.getElementById('update-request-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const fieldSelect = document.getElementById('update-field');
        const newValue = document.getElementById('update-new-value').value.trim();
        const reason = document.getElementById('update-reason').value.trim();
        const selectedOption = fieldSelect.selectedOptions[0];

        // Use a more robust way to get memberNo from the title
        const titleText = document.getElementById('update-request-title').textContent;
        const memberNoMatch = titleText.match(/\(([^)]+)\)/);
        const memberNo = memberNoMatch ? memberNoMatch[1] : null;

        const member = members.find(m => m.memberNo === memberNo);
        const needsApproval = selectedOption.dataset.approval === 'true';

        if (!member || !fieldSelect.value || !newValue) {
            showToast('Missing Fields', 'Please select a field and enter a new value.', 'error');
            return;
        }

        if (needsApproval && !reason) {
            showToast('Missing Reason', 'A reason is mandatory for updates requiring Admin approval.', 'error');
            return;
        }

        // Get the actual current value from the member object
        const currentValue = member[fieldSelect.value] !== undefined ? member[fieldSelect.value] : selectedOption.dataset.current;


        // 1. IF ADMIN APPROVAL IS REQUIRED
        if (needsApproval) {
            const requestData = {
                memberNo: member.memberNo,
                memberName: member.memberName,
                field: fieldSelect.value,
                currentValue: currentValue,
                newValue: newValue,
                reason: reason,
                requestedBy: 'head1' // Always submitted by the head1 user role
            };
            submitUpdateRequest(requestData);
        }
        // 2. IF DIRECT UPDATE IS ALLOWED
        else {
            const success = await directUpdateMember(member.memberNo, fieldSelect.value, newValue);
            // ⭐ NEW: Reload the summary after successful direct update
            if (success) {
                await loadFinancialSummary();
            }
        }

        closeModal('update-request-modal');
        this.reset();
        document.getElementById('current-value-display').style.display = 'none';
    });

    document.getElementById('update-field').addEventListener('change', function () {
        const display = document.getElementById('current-value-display');
        const currentValueText = document.getElementById('current-value-text');
        if (this.value) {
            const selectedOption = this.selectedOptions[0];
            currentValueText.textContent = selectedOption.dataset.current;
            display.style.display = 'block';
        } else {
            display.style.display = 'none';
        }
    });
});

window.loadFinancialSummary = loadFinancialSummary;