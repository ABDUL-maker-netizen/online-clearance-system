// assets/js/alerts.js

function successAlert(message){
    Swal.fire({
        icon: 'success',
        title: 'Approved!',
        text: message,
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: true,
        confirmButtonColor: '#28a745'
    });
}

function errorAlert(message){
    Swal.fire({
        icon: 'error',
        title: 'Rejected!',
        text: message,
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: true,
        confirmButtonColor: '#dc3545'
    });
}

function infoAlert(message){
    Swal.fire({
        icon: 'info',
        title: 'Information',
        text: message,
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: true,
        confirmButtonColor: '#17a2b8'
    });
}

function customAlert(message, type){
    // Check if Swal is loaded
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert2 is not loaded!');
        alert(message);
        return;
    }
    
    let iconType = 'success';
    let title = 'Success';
    let buttonColor = '#28a745';
    
    if(type === 'error' || type === 'rejected'){
        iconType = 'error';
        title = 'Rejected';
        buttonColor = '#dc3545';
    } else if(type === 'approved'){
        iconType = 'success';
        title = 'Approved';
        buttonColor = '#28a745';
    } else if(type === 'info'){
        iconType = 'info';
        title = 'Information';
        buttonColor = '#17a2b8';
    }
    
    Swal.fire({
        icon: iconType,
        title: title,
        text: message,
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: true,
        confirmButtonColor: buttonColor
    });
}