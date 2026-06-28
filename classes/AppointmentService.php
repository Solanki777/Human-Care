<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/EmailTemplate.php';

class AppointmentService
{
    /**
     * Create Appointment
     *
     * Used by:
     * 1. book_appointment.php
     * 2. MediMate AI
     */
    public static function createAppointment(
        int $patientId,
        int $doctorId,
        string $appointmentDate,
        string $appointmentTime,
        string $consultationType,
        string $reason,
        string $symptoms = ""
    ): array {

        // ------------------------------------------------------------
        // Database Connections
        // ------------------------------------------------------------

        $patients_conn = Database::getConnection('patients');
        $doctors_conn  = Database::getConnection('doctors');
        $admin_conn    = Database::getConnection('admin');

        // ------------------------------------------------------------
        // Start Transaction
        // ------------------------------------------------------------

        $admin_conn->begin_transaction();

        try {

            // ============================================================
            // Load Patient
            // ============================================================

            $stmt = $patients_conn->prepare("
                SELECT *
                FROM patients
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param("i", $patientId);
            $stmt->execute();

            $patient = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$patient) {

                throw new Exception(
                    "Patient record not found."
                );

            }

            // ============================================================
            // Load Doctor
            // ============================================================

            $stmt = $doctors_conn->prepare("
                SELECT *
                FROM doctors
                WHERE
                    id = ?
                    AND is_verified = 1
                    AND verification_status='approved'
                    AND is_deleted = 0
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $doctorId
            );

            $stmt->execute();

            $doctor = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$doctor) {

                throw new Exception(
                    "Selected doctor is unavailable."
                );

            }

            // ============================================================
            // Check Doctor Conflict
            // ============================================================

            $stmt = $admin_conn->prepare("
                SELECT id
                FROM appointments
                WHERE
                    doctor_id=?
                    AND appointment_date=?
                    AND appointment_time=?
                    AND status IN ('pending','approved')
                LIMIT 1
            ");

            $stmt->bind_param(
                "iss",
                $doctorId,
                $appointmentDate,
                $appointmentTime
            );

            $stmt->execute();

            $conflict = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($conflict) {

                throw new Exception(
                    "The selected doctor already has an appointment at this time."
                );

            }

            // ============================================================
            // Check Patient Conflict
            // ============================================================

            $stmt = $admin_conn->prepare("
                SELECT id
                FROM appointments
                WHERE
                    patient_id=?
                    AND appointment_date=?
                    AND appointment_time=?
                    AND status IN ('pending','approved')
                LIMIT 1
            ");

            $stmt->bind_param(
                "iss",
                $patientId,
                $appointmentDate,
                $appointmentTime
            );

            $stmt->execute();

            $conflict = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($conflict) {

                throw new Exception(
                    "You already have another appointment at this time."
                );

            }

            // ============================================================
            // Build Variables
            // ============================================================

            $patient_name =
                $patient['first_name'] .
                ' ' .
                $patient['last_name'];

            $doctor_name =
                $doctor['first_name'] .
                ' ' .
                $doctor['last_name'];

            $patient_age =
                (new DateTime(
                    $patient['dob']
                ))->diff(
                    new DateTime()
                )->y;

                





                            // ============================================================
            // Create Appointment
            // ============================================================

            $stmt = $admin_conn->prepare("
                INSERT INTO appointments (

                    patient_id,
                    patient_name,
                    patient_email,
                    patient_phone,
                    patient_age,

                    doctor_id,
                    doctor_name,
                    doctor_specialty,

                    appointment_date,
                    appointment_time,

                    consultation_type,

                    reason_for_visit,

                    symptoms,

                    status

                )

                VALUES(

                    ?,?,?,?,?,

                    ?,?,?,

                    ?,?,

                    ?,

                    ?,

                    ?,

                    'pending'

                )
            ");

            $stmt->bind_param(

                "issssisssssss",

                $patientId,

                $patient_name,

                $patient['email'],

                $patient['phone'],

                $patient_age,

                $doctorId,

                $doctor_name,

                $doctor['specialty'],

                $appointmentDate,

                $appointmentTime,

                $consultationType,

                $reason,

                $symptoms

            );

            if (!$stmt->execute()) {

                throw new Exception(
                    $stmt->error
                );

            }

            $appointment_id = $stmt->insert_id;

            $stmt->close();

            // ============================================================
            // Appointment History
            // ============================================================

            $stmt = $admin_conn->prepare("

                INSERT INTO appointment_history(

                    appointment_id,

                    action,

                    performed_by,

                    performed_by_type,

                    new_status,

                    notes

                )

                VALUES(

                    ?,

                    'created',

                    ?,

                    'patient',

                    'pending',

                    'Appointment booked through patient portal.'

                )

            ");

            $stmt->bind_param(

                "ii",

                $appointment_id,

                $patientId

            );

            if (!$stmt->execute()) {

                throw new Exception(
                    $stmt->error
                );

            }

            $stmt->close();

            // ============================================================
            // Admin Notification
            // ============================================================

            $message =
                "New appointment request from "
                . $patient_name .
                " for Dr. "
                . $doctor_name;

            $stmt = $admin_conn->prepare("

                INSERT INTO appointment_notifications(

                    appointment_id,

                    recipient_type,

                    recipient_id,

                    notification_type,

                    message

                )

                VALUES(

                    ?,

                    'admin',

                    1,

                    'new',

                    ?

                )

            ");

            $stmt->bind_param(

                "is",

                $appointment_id,

                $message

            );

            if (!$stmt->execute()) {

                throw new Exception(
                    $stmt->error
                );

            }

            $stmt->close();

            // ============================================================
            // Prepare Email Data
            // ============================================================

            $emailData = [

                'patient_name' => $patient_name,

                'doctor_name' => $doctor_name,

                'doctor_specialty' => $doctor['specialty'],

                'appointment_date' => date(
                    'F d, Y',
                    strtotime($appointmentDate)
                ),

                'appointment_time' => date(
                    'h:i A',
                    strtotime($appointmentTime)
                ),

                'consultation_type' => $consultationType,

                'reason' => $reason,

                'patient_phone' => $patient['phone'],

                'patient_email' => $patient['email'],

                'created_at' => date(
                    'F d, Y h:i A'
                )

            ];
                        // ============================================================
            // Send Patient Email
            // ============================================================

            // try {

            //     $patientEmail = EmailTemplate::appointmentPending($emailData);

            //     EmailTemplate::send(
            //         $patient['email'],
            //         'Appointment Request Submitted',
            //         $patientEmail
            //     );

            // } catch (Exception $e) {

            //     error_log(
            //         "Patient email failed: " .
            //         $e->getMessage()
            //     );

            // }

            // ============================================================
            // Send Admin Email
            // ============================================================

            // try {

            //     $adminEmail = EmailTemplate::newAppointmentAdmin($emailData);

            //     EmailTemplate::send(
            //         ADMIN_EMAIL,
            //         'New Appointment Request',
            //         $adminEmail
            //     );

            // } catch (Exception $e) {

            //     error_log(
            //         "Admin email failed: " .
            //         $e->getMessage()
            //     );

            // }

            // ============================================================
            // Commit Transaction
            // ============================================================

            $admin_conn->commit();

            return [

                'success' => true,

                'appointment_id' => $appointment_id,

                'patient' => $patient,

                'doctor' => $doctor,

                'message' =>
                    'Appointment request submitted successfully. '
                    . 'Our admin team will review your request and '
                    . 'you will receive a confirmation email after approval.'

            ];

        }

        catch (Exception $e) {

            $admin_conn->rollback();

            error_log(
                "AppointmentService Error: " .
                $e->getMessage()
            );

            return [

                'success' => false,

                'message' => $e->getMessage()

            ];

        }

    }

    // ============================================================
    // Get Appointment
    // ============================================================

    public static function getAppointment(
        int $appointmentId
    ): ?array {

        $admin_conn = Database::getConnection('admin');

        $stmt = $admin_conn->prepare("

            SELECT *

            FROM appointments

            WHERE id=?

            LIMIT 1

        ");

        $stmt->bind_param(
            "i",
            $appointmentId
        );

        $stmt->execute();

        $appointment = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        return $appointment ?: null;

    }

    // ============================================================
    // Cancel Appointment
    // ============================================================

    public static function cancelAppointment(
        int $appointmentId
    ): bool {

        $admin_conn = Database::getConnection('admin');

        $stmt = $admin_conn->prepare("

            UPDATE appointments

            SET status='cancelled'

            WHERE id=?

        ");

        $stmt->bind_param(
            "i",
            $appointmentId
        );

        $success = $stmt->execute();

        $stmt->close();

        return $success;

    }

}