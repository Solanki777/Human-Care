<?php
/**
 * Email Template Manager
 */
class EmailTemplate {
    
    private static $baseStyle = "
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        .info-box { background: white; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; }
        table.details { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table.details td { padding: 10px; border-bottom: 1px solid #eee; }
        table.details td:first-child { font-weight: bold; color: #666; width: 40%; }
    ";
    
    /**
     * Wrap content in email template
     */
    private static function wrap($content, $title) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>" . self::$baseStyle . "</style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>$title</h1>
                </div>
                <div class='content'>
                    $content
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Appointment pending email (to patient)
     */
    public static function appointmentPending($data) {
        $content = "
            <p>Dear {$data['patient_name']},</p>
            
            <p>Thank you for booking an appointment with " . APP_NAME . ". Your appointment request has been received and is currently under review.</p>
            
            <div class='info-box'>
                <h3 style='margin-top: 0;'>Appointment Details:</h3>
                <table class='details'>
                    <tr>
                        <td>Doctor:</td>
                        <td>Dr. {$data['doctor_name']}</td>
                    </tr>
                    <tr>
                        <td>Specialty:</td>
                        <td>{$data['doctor_specialty']}</td>
                    </tr>
                    <tr>
                        <td>Requested Date:</td>
                        <td>{$data['appointment_date']}</td>
                    </tr>
                    <tr>
                        <td>Requested Time:</td>
                        <td>{$data['appointment_time']}</td>
                    </tr>
                    <tr>
                        <td>Reason:</td>
                        <td>{$data['reason']}</td>
                    </tr>
                </table>
            </div>
            
            <p><strong>What happens next?</strong></p>
            <ul>
                <li>Our admin team will review your appointment request</li>
                <li>You will receive a confirmation email once approved</li>
                <li>The appointment will appear in your dashboard</li>
            </ul>
            
            <p><strong>Expected verification time:</strong> Within 24 hours</p>
            
            <p style='text-align: center;'>
                <a href='" . APP_URL . "/login.php' class='button'>View My Dashboard</a>
            </p>
            
            <p>If you have any questions, please contact our support team.</p>
            
            <p>Best regards,<br>
            <strong>" . APP_NAME . " Team</strong></p>
        ";
        
        return self::wrap($content, '⏳ Appointment Request Received');
    }
    
    /**
     * Appointment approved email (to patient)
     */
    public static function appointmentApproved($data) {
        $content = "
            <p>Dear {$data['patient_name']},</p>
            
            <p>Great news! Your appointment has been <strong>confirmed</strong>.</p>
            
            <div class='info-box' style='border-left-color: #10b981; background: #d1fae5;'>
                <h3 style='margin-top: 0; color: #065f46;'>✅ Confirmed Appointment</h3>
                <table class='details'>
                    <tr>
                        <td>Doctor:</td>
                        <td>Dr. {$data['doctor_name']}</td>
                    </tr>
                    <tr>
                        <td>Specialty:</td>
                        <td>{$data['doctor_specialty']}</td>
                    </tr>
                    <tr>
                        <td>Date:</td>
                        <td><strong>{$data['appointment_date']}</strong></td>
                    </tr>
                    <tr>
                        <td>Time:</td>
                        <td><strong>{$data['appointment_time']}</strong></td>
                    </tr>
                    <tr>
                        <td>Consultation Type:</td>
                        <td>{$data['consultation_type']}</td>
                    </tr>
                    <tr>
                        <td>Location:</td>
                        <td>" . APP_NAME . "</td>
                    </tr>
                </table>
            </div>
            
            <p><strong>Before your appointment:</strong></p>
            <ul>
                <li>Please arrive 10 minutes early for registration</li>
                <li>Bring your ID and previous medical records (if any)</li>
                <li>Write down any questions you want to ask the doctor</li>
            </ul>
            
            <p style='text-align: center;'>
                <a href='" . APP_URL . "/dashboard.php' class='button'>View My Appointments</a>
            </p>
            
            <p>If you need to reschedule or cancel, please contact us at least 24 hours in advance.</p>
            
            <p>Best regards,<br>
            <strong>" . APP_NAME . " Team</strong></p>
        ";
        
        return self::wrap($content, '✅ Appointment Confirmed');
    }
    
    /**
     * Appointment rejected email (to patient)
     */
    public static function appointmentRejected($data) {
        $content = "
            <p>Dear {$data['patient_name']},</p>
            
            <p>We regret to inform you that your appointment request could not be approved.</p>
            
            <div class='info-box' style='border-left-color: #ef4444; background: #fee2e2;'>
                <h3 style='margin-top: 0; color: #991b1b;'>Appointment Details:</h3>
                <table class='details'>
                    <tr>
                        <td>Doctor:</td>
                        <td>Dr. {$data['doctor_name']}</td>
                    </tr>
                    <tr>
                        <td>Requested Date:</td>
                        <td>{$data['appointment_date']}</td>
                    </tr>
                    <tr>
                        <td>Requested Time:</td>
                        <td>{$data['appointment_time']}</td>
                    </tr>
                </table>
                
                <p style='margin-top: 15px;'><strong>Reason:</strong><br>
                {$data['rejection_reason']}</p>
            </div>
            
            <p><strong>What you can do:</strong></p>
            <ul>
                <li>Try booking a different date or time</li>
                <li>Choose a different doctor in the same specialty</li>
                <li>Contact our support team for assistance</li>
            </ul>
            
            <p style='text-align: center;'>
                <a href='" . APP_URL . "/book_appointment.php' class='button'>Book Another Appointment</a>
            </p>
            
            <p>We apologize for any inconvenience.</p>
            
            <p>Best regards,<br>
            <strong>" . APP_NAME . " Team</strong></p>
        ";
        
        return self::wrap($content, 'Appointment Update');
    }
    
    /**
     * New appointment notification (to admin)
     */
    public static function newAppointmentAdmin($data) {
        $content = "
            <p>A new appointment request has been submitted and requires your review.</p>
            
            <div class='info-box'>
                <h3 style='margin-top: 0;'>Appointment Details:</h3>
                <table class='details'>
                    <tr>
                        <td>Patient:</td>
                        <td>{$data['patient_name']}</td>
                    </tr>
                    <tr>
                        <td>Contact:</td>
                        <td>{$data['patient_phone']}<br>{$data['patient_email']}</td>
                    </tr>
                    <tr>
                        <td>Doctor:</td>
                        <td>Dr. {$data['doctor_name']}</td>
                    </tr>
                    <tr>
                        <td>Date & Time:</td>
                        <td>{$data['appointment_date']} at {$data['appointment_time']}</td>
                    </tr>
                    <tr>
                        <td>Reason:</td>
                        <td>{$data['reason']}</td>
                    </tr>
                    <tr>
                        <td>Submitted:</td>
                        <td>{$data['created_at']}</td>
                    </tr>
                </table>
            </div>
            
            <p style='text-align: center;'>
                <a href='" . APP_URL . "/admin_appointments.php' class='button'>Review Appointment</a>
            </p>
            
            <p>Please review and approve/reject this appointment request as soon as possible.</p>
        ";
        
        return self::wrap($content, '🔔 New Appointment Request');
    }
    
    /**
     * Send email
     */
    public static function send($to, $subject, $content) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">" . "\r\n";
        
        return mail($to, $subject, $content, $headers);
    }
}