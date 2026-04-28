    </div> <!-- container-fluid close -->
    
    <!-- ফুটার সেকশন -->
    <footer class="bg-light text-center text-lg-start mt-4 py-3 border-top">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 text-md-start mb-2 mb-md-0">
                    <span class="text-muted">
                        &copy; <?php echo date('Y'); ?> 
                        <a href="https://github.com/ekparvez" target="_blank" class="text-decoration-none text-primary">
                            E.K Parvez
                        </a>
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted">
                        ISP বিলিং সিস্টেম v2.0 | 
                        <a href="#" data-bs-toggle="modal" data-bs-target="#aboutModal" class="text-decoration-none text-info">
                            <i class="fas fa-info-circle"></i> সম্পর্কে
                        </a>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- অ্যাবাউট মডাল -->
    <div class="modal fade" id="aboutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-wifi"></i> ISP বিলিং সিস্টেম</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-bolt fa-3x text-primary"></i>
                        <h4 class="mt-2">ISP বিলিং সিস্টেম v2.0</h4>
                    </div>
                    <p><strong>ডেভেলপার:</strong> E.K Parvez</p>
                    <p><strong>ইমেইল:</strong> bdtechnology2019@gmail.com</p>
                    <p><strong>গিটহাব:</strong> <a href="https://github.com/ekparvez" target="_blank">github.com/ekparvez</a></p>
                    <hr>
                    <p><strong>ফিচারসমূহ:</strong></p>
                    <ul>
                        <li>ক্লায়েন্ট ম্যানেজমেন্ট</li>
                        <li>বিল জেনারেশন ও ট্র্যাকিং</li>
                        <li>অনলাইন পেমেন্ট গ্রহণ</li>
                        <li>এসএমএস ও WhatsApp নোটিফিকেশন</li>
                        <li>ফাইন্যান্স ট্র্যাকিং</li>
                        <li>রিপোর্ট ও বিশ্লেষণ</li>
                    </ul>
                    <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> সকল অধিকার সংরক্ষিত।</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
                </div>
            </div>
        </div>
    </div>

    <!-- স্ক্রোল টু টপ বাটন -->
    <button onclick="scrollToTop()" id="scrollTopBtn" class="btn btn-primary rounded-circle position-fixed bottom-0 end-0 m-4" style="display: none; width: 50px; height: 50px; z-index: 999;">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables (যদি ব্যবহার করেন) -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- কাস্টম জাভাস্ক্রিপ্ট -->
    <script src="../assets/js/script.js"></script>

    <script>
    // স্ক্রোল টু টপ ফাংশন
    window.onscroll = function() {
        scrollFunction();
    };

    function scrollFunction() {
        const scrollBtn = document.getElementById("scrollTopBtn");
        if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
            scrollBtn.style.display = "block";
        } else {
            scrollBtn.style.display = "none";
        }
    }

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // অটো হাইড অ্যালার্ট
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(alert => {
            if (!alert.classList.contains('alert-permanent')) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        });
    }, 5000);

    // কনফার্মেশন ডায়ালগ
    document.querySelectorAll('.confirm-action').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm || 'আপনি কি নিশ্চিত?')) {
                e.preventDefault();
            }
        });
    });

    // টুলটিপ সক্রিয়
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // পপওভার সক্রিয়
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // কীবোর্ড শর্টকাট
    document.addEventListener('keydown', function(e) {
        // Ctrl+S - সেভ
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const saveBtn = document.querySelector('[type="submit"]');
            if (saveBtn) saveBtn.click();
        }
        // Ctrl+F - সার্চ ফোকাস
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('input[type="search"], input[name="search"]');
            if (searchInput) searchInput.focus();
        }
        // Esc - মডাল বন্ধ
        if (e.key === 'Escape') {
            const modal = document.querySelector('.modal.show');
            if (modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) modalInstance.hide();
            }
        }
    });

    // লোডিং স্পিনার
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> প্রসেস হচ্ছে...';
            }
        });
    });
    </script>

    <style>
    /* স্ক্রোল টু টপ বাটন স্টাইল */
    #scrollTopBtn {
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        transition: all 0.3s;
        opacity: 0.8;
    }
    #scrollTopBtn:hover {
        opacity: 1;
        transform: scale(1.1);
        box-shadow: 0 6px 15px rgba(0,0,0,0.3);
    }

    /* ফুটার স্টাইল */
    footer {
        font-size: 14px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }
    footer a {
        transition: all 0.3s;
    }
    footer a:hover {
        color: #0a58ca !important;
        text-decoration: underline !important;
    }

    /* অ্যালার্ট অ্যানিমেশন */
    .alert {
        animation: slideIn 0.3s ease;
    }
    @keyframes slideIn {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* লোডিং স্পিনার */
    .fa-spinner {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    </style>
</body>
</html>