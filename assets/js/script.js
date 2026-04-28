$(document).ready(function() {
    // টুলটিপ সক্রিয় করুন
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // অ্যালার্ট অটো হাইড
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // ক্লায়েন্ট আইডি অনুসন্ধান
    $('#searchClient').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#clientTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // কনফার্মেশন ডায়ালগ
    $('.confirm-delete').click(function(e) {
        if(!confirm('আপনি কি নিশ্চিত?')) {
            e.preventDefault();
        }
    });
    
    // পেমেন্ট ফর্ম ভ্যালিডেশন
    $('#paymentForm').submit(function(e) {
        var paidAmount = parseFloat($('#paid_amount').val());
        var billAmount = parseFloat($('#bill_amount').val());
        
        if(paidAmount > billAmount) {
            if(!confirm('পরিশোধের পরিমাণ বিলের চেয়ে বেশি। কি চালিয়ে যাবেন?')) {
                e.preventDefault();
            }
        }
    });
    
    // ডাইনামিক ড্যাশবোর্ড রিফ্রেশ
    function refreshDashboard() {
        $.ajax({
            url: 'api/get_dashboard_data.php',
            method: 'GET',
            success: function(data) {
                // ড্যাশবোর্ড ডাটা আপডেট
                $('#totalClients').text(data.total_clients);
                $('#totalDue').text('৳' + data.total_due);
                $('#monthlyCollection').text('৳' + data.monthly_collection);
            }
        });
    }
	
	// রিপোর্ট এক্সপোর্ট ফাংশন
function exportToExcel() {
    var table = document.querySelector('.table');
    if (!table) {
        alert('কোনো টেবিল পাওয়া যায়নি!');
        return;
    }
    
    var html = table.outerHTML;
    var blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'isp_report_' + new Date().toISOString().slice(0,10) + '.xls';
    link.click();
}

// পিডিএফ এক্সপোর্ট (যদি প্রয়োজন হয়)
function exportToPDF() {
    // এই ফাংশনের জন্য html2pdf লাইব্রেরি প্রয়োজন
    // আপনি চাইলে পরে যোগ করতে পারেন
    alert('PDF এক্সপোর্ট শীঘ্রই আসছে...');
}

// প্রিন্ট ফাংশন
window.print = function() {
    var printContents = document.querySelector('.card-body').innerHTML;
    var originalContents = document.body.innerHTML;
    
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}
	
	
	// CSV ফাইল ভ্যালিডেশন
function validateCSVFile(input) {
    const file = input.files[0];
    const fileExt = file.name.split('.').pop().toLowerCase();
    
    if (fileExt !== 'csv') {
        alert('শুধুমাত্র CSV ফাইল আপলোড করুন!');
        input.value = '';
        return false;
    }
    
    if (file.size > 5 * 1024 * 1024) { // 5MB limit
        alert('ফাইলের সাইজ ৫MB এর কম হতে হবে!');
        input.value = '';
        return false;
    }
    
    return true;
}

// ড্র্যাগ ও ড্রপ সাপোর্ট
function setupDragDrop() {
    const dropZone = document.getElementById('dropZone');
    
    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            dropZone.classList.add('highlight');
        }
        
        function unhighlight(e) {
            dropZone.classList.remove('highlight');
        }
        
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            const fileInput = document.getElementById('csv_file');
            
            if (files.length > 0) {
                fileInput.files = files;
                validateCSVFile(fileInput);
            }
        }
    }
}

// পৃষ্ঠা লোড হলে কল করুন
document.addEventListener('DOMContentLoaded', function() {
    setupDragDrop();
});
function selectMethod(method) {
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected', 'border-success');
    });
    
    document.getElementById('card-' + method).classList.add('selected', 'border-success');
    document.querySelector(`input[name="payment_method"][value="${method}"]`).checked = true;
    
    // সব ইনফো ডিভ লুকান
    document.getElementById('mobileBankingInfo').style.display = 'none';
    document.getElementById('bankInfo').style.display = 'none';
    document.getElementById('bakiInfo').style.display = 'none';
    
    // পদ্ধতি অনুযায়ী দেখান
    if (method === 'bkash' || method === 'nagad' || method === 'rocket') {
        document.getElementById('mobileBankingInfo').style.display = 'block';
    } else if (method === 'bank') {
        document.getElementById('bankInfo').style.display = 'block';
    } else if (method === 'baki') {
        document.getElementById('bakiInfo').style.display = 'block';
    }
    
    // বাকি সিলেক্ট করলে বিশেষ বার্তা
    if (method === 'baki') {
        document.getElementById('paymentNote').innerHTML = '<i class="fas fa-info-circle"></i> আপনি বাকি হিসেবে রাখছেন। এই টাকা বকেয়া হিসেবে গণনা হবে এবং পরে আদায় করতে হবে।';
    } else {
        calculateRemaining();
    }
}	
  
// লগ ফাংশন
function logActivity($conn, $action, $description, $status = 'info', $data = null) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $username = $_SESSION['username'] ?? 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $execution_time = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    
    $data_json = $data ? json_encode($data) : null;
    
    $query = "INSERT INTO activity_logs (user_id, username, action, description, ip_address, user_agent, page, method, data, status, execution_time) 
              VALUES ('$user_id', '$username', '$action', '$description', '$ip', '$user_agent', '$page', '$method', " . ($data_json ? "'$data_json'" : "NULL") . ", '$status', '$execution_time')";
    
    return mysqli_query($conn, $query);
}

  
    // প্রতি ৫ মিনিট পর ড্যাশবোর্ড রিফ্রেশ
    setInterval(refreshDashboard, 300000);
});
