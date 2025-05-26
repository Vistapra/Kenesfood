// Definisikan base_url di bagian atas script
var base_url = site_url(''); // Menggunakan fungsi site_url() dari Smarty

$(document).ready(function () {
    // OTP Input Handling
    $(".otp-input").on("keyup", function (e) {
        var key = e.key;
        var input = $(this);

        // Skip if not a number
        if (!/^[0-9]+$/.test(input.val()) && input.val() !== "") {
            input.val("");
            return;
        }

        // Auto-focus next input
        if (input.val() !== "" && input.data("next")) {
            $("#" + input.data("next")).focus();
        }

        // Handle backspace
        if (key === "Backspace" && input.data("previous")) {
            $("#" + input.data("previous")).focus();
        }

        // Combine all inputs into the hidden field
        combineOtpValues();
    });

    // Handle paste event for OTP
    $(".otp-input").on("paste", function (e) {
        e.preventDefault();
        var paste = (
            e.originalEvent.clipboardData || window.clipboardData
        ).getData("text");

        if (/^\d+$/.test(paste)) {
            var digits = paste.split("");

            $(".otp-input").each(function (index) {
                if (index < digits.length) {
                    $(this).val(digits[index]);
                }
            });

            // Focus the last input or the next empty one
            var lastFilledInput = $(".otp-input")
                .filter(function () {
                    return $(this).val() !== "";
                })
                .last();

            var nextEmpty = $(".otp-input")
                .filter(function () {
                    return $(this).val() === "";
                })
                .first();

            if (nextEmpty.length) {
                nextEmpty.focus();
            } else {
                lastFilledInput.focus();
            }

            combineOtpValues();
        }
    });

    function combineOtpValues() {
        var otp = "";
        $(".otp-input").each(function () {
            otp += $(this).val();
        });
        $("#otp").val(otp);
    }

    // Navigation between steps
    $("#btn-back-to-phone").on("click", function() {
        $("#step2").removeClass("active");
        $("#step1").addClass("active");
        stopCountdown();
    });

    $("#btn-back-to-otp").on("click", function() {
        $("#step3").removeClass("active");
        $("#step2").addClass("active");
    });

    // Kirim OTP
    $("#btn-send-otp").on("click", function () {
        var phone = $("#phone").val();
        var otpType = $("#otp_type").val();

        if (!phone) {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Silakan masukkan nomor telepon",
                confirmButtonColor: "#662a0c",
            });
            return;
        }

        if (!validatePhoneNumber(phone)) {
            Swal.fire({
                icon: "error",
                title: "Format Nomor Tidak Valid",
                text: "Pastikan nomor telepon Anda dalam format yang benar",
                confirmButtonColor: "#662a0c",
            });
            return;
        }

        // Show loading state
        var btn = $(this);
        btn
            .prop("disabled", true)
            .html(
                '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Mengirim...'
            );

        $.ajax({
            url: site_url('member/register/send_otp'),
            type: "POST",
            data: {
                phone: phone,
                otp_type: otpType,
            },
            dataType: "json",
            timeout: 30000, // 30 detik timeout
            success: function (response) {
                console.log("Response received:", response);

                if (response.status === "success") {
                    // Pindah ke step verifikasi OTP
                    $("#step1").removeClass("active");
                    $("#step2").addClass("active");

                    // Format phone number for display
                    var displayPhone = formatPhoneNumberForDisplay(phone);
                    $("#display-phone").text(displayPhone);

                    // Reset OTP inputs
                    $(".otp-input").val("");
                    $("#otp-input-1").focus();

                    // Mulai countdown
                    startCountdown();

                    Swal.fire({
                        icon: "success",
                        title: "OTP Terkirim",
                        text: response.message,
                        confirmButtonColor: "#662a0c",
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: response.message || "Terjadi kesalahan. Silakan coba lagi.",
                        confirmButtonColor: "#662a0c",
                    });
                }
                btn
                    .prop("disabled", false)
                    .html('<i class="fas fa-paper-plane me-2"></i> Kirim OTP');
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);

                var errorMessage = "Terjadi kesalahan. Silakan coba lagi.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: errorMessage,
                    confirmButtonColor: "#662a0c",
                });
                btn
                    .prop("disabled", false)
                    .html('<i class="fas fa-paper-plane me-2"></i> Kirim OTP');
            },
        });
    });

    // Kirim ulang OTP
    $("#btn-resend").on("click", function() {
        if ($(this).hasClass("disabled")) return;
        $("#btn-send-otp").click();
    });
    
    // Verifikasi OTP
    $("#btn-verify-otp").on("click", function () {
        var otp = $("#otp").val();

        if (!otp || otp.length !== 6) {
            Swal.fire({
                icon: "error",
                title: "OTP Tidak Lengkap",
                text: "Silakan masukkan 6 digit kode OTP",
                confirmButtonColor: "#662a0c",
            });
            return;
        }

        // Show loading state
        var btn = $(this);
        btn
            .prop("disabled", true)
            .html(
                '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Memverifikasi...'
            );

        $.ajax({
            url: site_url('member/register/verify_otp'),
            type: "POST",
            data: {
                otp: otp,
            },
            dataType: "json",
            success: function (response) {
                if (response.status === "success") {
                    // Pindah ke step registrasi
                    $("#step2").removeClass("active");
                    $("#step3").addClass("active");
                    
                    // Tampilkan nomor yang terverifikasi
                    $("#verified-phone").text(formatPhoneNumberForDisplay(response.phone));
                    
                    Swal.fire({
                        icon: "success",
                        title: "Verifikasi Berhasil",
                        text: "Silakan lengkapi data diri Anda untuk menyelesaikan registrasi.",
                        confirmButtonColor: "#662a0c",
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Verifikasi Gagal",
                        text: response.message,
                        confirmButtonColor: "#662a0c",
                    });
                }
                btn
                    .prop("disabled", false)
                    .html('<i class="fas fa-check-circle me-2"></i> Verifikasi');
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", status, error);

                let errorMessage = "Terjadi kesalahan. Silakan coba lagi.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: errorMessage,
                    confirmButtonColor: "#662a0c",
                });
                btn
                    .prop("disabled", false)
                    .html('<i class="fas fa-check-circle me-2"></i> Verifikasi');
            },
        });
    });
    
    // Submit registrasi
    $("#btn-register").on("click", function() {
        // Validasi form
        if (!$("#fullname").val()) {
            Swal.fire({
                icon: "error",
                title: "Data Tidak Lengkap",
                text: "Nama lengkap wajib diisi",
                confirmButtonColor: "#662a0c",
            });
            return;
        }
        
        if (!$("#terms").is(":checked")) {
            Swal.fire({
                icon: "error",
                title: "Syarat dan Ketentuan",
                text: "Anda harus menyetujui syarat dan ketentuan untuk melanjutkan",
                confirmButtonColor: "#662a0c",
            });
            return;
        }
        
        // Show loading state
        var btn = $(this);
        btn
            .prop("disabled", true)
            .html(
                '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Memproses...'
            );
        
        // Ambil data form
        var formData = {
            fullname: $("#fullname").val(),
            email: $("#email").val(),
            dob: $("#dob").val(),
            address: $("#address").val()
        };
        
        $.ajax({
            url: site_url('member/register/submit_registration'),
            type: "POST",
            data: formData,
            dataType: "json",
            success: function(response) {
                if (response.status === "success") {
                    // Tampilkan pesan sukses dengan password
                    Swal.fire({
                        icon: "success",
                        title: "Registrasi Berhasil!",
                        html: `
                            <p>${response.message}</p>
                            <div class="alert alert-info mt-3">
                                <strong>Password Anda:</strong> <span class="text-monospace">${response.password}</span>
                                <br><small>Simpan password ini untuk login pada kesempatan berikutnya</small>
                            </div>
                        `,
                        confirmButtonColor: "#662a0c",
                        allowOutsideClick: false,
                    }).then(function() {
                        // Redirect ke dashboard
                        window.location.href = response.redirect;
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Registrasi Gagal",
                        text: response.message,
                        confirmButtonColor: "#662a0c",
                    });
                    btn
                        .prop("disabled", false)
                        .html('<i class="fas fa-user-plus me-2"></i> Daftar Sekarang');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                
                let errorMessage = "Terjadi kesalahan. Silakan coba lagi.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: errorMessage,
                    confirmButtonColor: "#662a0c",
                });
                btn
                    .prop("disabled", false)
                    .html('<i class="fas fa-user-plus me-2"></i> Daftar Sekarang');
            }
        });
    });
    
    // Helper functions
    function validatePhoneNumber(phone) {
        // Basic validation for Indonesian phone numbers
        var phonePattern = new RegExp(
            "^(0|62|\\+62)[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]([0-9])?([0-9])?([0-9])?([0-9])?$"
        );
        return phonePattern.test(phone);
    }
    
    function formatPhoneNumberForDisplay(phone) {
        // Format: 081234567890 -> 0812-****-7890
        if (phone.length < 8) return phone;

        // Remove country code if present
        if (phone.startsWith("+62")) {
            phone = "0" + phone.substring(3);
        } else if (phone.startsWith("62")) {
            phone = "0" + phone.substring(2);
        }

        // Format with masking in the middle
        const firstPart = phone.substring(0, 4);
        const lastPart = phone.substring(phone.length - 4);
        const maskedLength = phone.length - 8;
        // Replace .repeat() with a simple loop to avoid compatibility issues
        let maskedPart = "";
        for (let i = 0; i < maskedLength; i++) {
            maskedPart += "*";
        }

        return firstPart + "-" + maskedPart + "-" + lastPart;
    }

    // Countdown function
    let countdownInterval;
    function startCountdown() {
        var seconds = 60;
        $("#countdown").removeClass("d-none");
        $("#btn-resend").addClass("disabled");

        countdownInterval = setInterval(function () {
            seconds--;
            $("#countdown-timer").text(seconds);

            if (seconds <= 0) {
                stopCountdown();
            }
        }, 1000);
    }

    function stopCountdown() {
        clearInterval(countdownInterval);
        $("#countdown").addClass("d-none");
        $("#btn-resend").removeClass("disabled");
    }
});