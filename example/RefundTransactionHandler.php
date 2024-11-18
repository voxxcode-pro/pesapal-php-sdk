<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Process Refund</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .content-wrapper {
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease-in;
    }

    .content-visible {
        display: block;
        opacity: 1;
    }

    /* Powered By Container Styling */
    .powered-by-container {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 50px;
        padding: 10px;
        background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(230, 230, 230, 0.5) 50%, rgba(255, 255, 255, 0) 100%);
        border-radius: 5px;
        position: relative;
    }

    .powered-by-container::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 5%;
        width: 40%;
        height: 1px;
        background-color: #eaeaea;
        transform: translateY(-50%);
    }

    .powered-by-container::after {
        content: '';
        position: absolute;
        top: 50%;
        right: 5%;
        width: 40%;
        height: 1px;
        background-color: #eaeaea;
        transform: translateY(-50%);
    }

    .powered-by-text {
        margin-right: 10px;
        font-weight: bold;
        color: #555;
        z-index: 1;
        background-color: #fff;
        padding: 0 5px;
    }

    .pesapal-logo {
        max-height: 30px;
        z-index: 1;
    }
    </style>
</head>

<body>

    <!-- Refund Form -->
    <div class="container content-wrapper" id="refundContainer">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="mt-5 text-center">Process Refund</h2>
                <p class="text-muted text-center">Enter the details to process the refund.</p>

                <!-- Refund Rules -->
                <div class="alert alert-info">
                    <strong>Refund Policy:</strong>
                    <ul>
                        <li>Refunds must be approved by the merchant.</li>
                        <li>The amount refunded cannot exceed the amount originally collected.</li>
                        <li>Only payments with the status of COMPLETED can be refunded.</li>
                        <li>Partial or full refunds are possible for payment card transactions; only full refunds are
                            allowed for mobile payments.</li>
                        <li>Refunds will be issued in the currency of the original payment.</li>
                        <li>Only one refund is possible per payment transaction.</li>
                    </ul>
                </div>
                <!-- Success Message -->
                <div id="refundSuccessMessage" class="alert alert-success mt-4" style="display: none;">
                    Refund processed successfully!
                </div>

                <!-- Error Message -->
                <div id="refundErrorMessage" class="alert alert-danger mt-4" style="display: none;">
                    There was an error processing the refund. Please try again.
                </div>

                <!-- Refund Form -->
                <form id="refundForm">
                    <div class="form-group">
                        <label for="orderTrackingIdField">Order Tracking ID</label>
                        <input type="text" class="form-control" id="orderTrackingIdField" name="order_tracking_id"
                            placeholder="Enter Order Tracking ID" required>
                    </div>
                    <div class="form-group">
                        <label for="amountField">Refund Amount</label>
                        <input type="text" step="0.01" class="form-control" id="amountField" name="amount"
                            placeholder="Enter Refund Amount" required>
                        <small id="amountHelp" class="form-text text-muted">
                            Enter the amount that should be refunded.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="usernameField">Username</label>
                        <input type="text" class="form-control" id="usernameField" name="username"
                            placeholder="Enter Username" required>
                        <small id="usernameHelp" class="form-text text-muted">
                            Identity of the user who has initiated the refund.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="remarksField">Remarks</label>
                        <textarea class="form-control" id="remarksField" name="remarks" rows="3"
                            placeholder="Enter Remarks" required></textarea>
                        <small id="remarksHelp" class="form-text text-muted">
                            Provide a brief description of the reason for the refund.
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block mb-4" id="processRefundButton">Process
                        Refund</button>
                </form>
                <!-- "Powered by Pesapal" Section -->
                <div class="powered-by-container">
                    <span class="powered-by-text">Powered by</span>
                    <img src="images/pesapal.png" alt="Pesapal Logo" class="pesapal-logo">
                </div>
            </div>
        </div>
    </div>

    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <script>
    $(document).ready(function() {
        // Show content after page load
        $('#refundContainer').addClass('content-visible');

        // Handle form submission
        $('#refundForm').on('submit', function(e) {
            e.preventDefault();

            const orderTrackingId = $('#orderTrackingIdField').val();
            const amount = $('#amountField').val();
            const username = $('#usernameField').val();
            const remarks = $('#remarksField').val();

            // Validate input
            if (!orderTrackingId || !amount || !username || !remarks) {
                $('#refundErrorMessage').text('Please fill in all required fields.').show();
                $('#refundSuccessMessage').hide();
                return;
            }


            // Prepare data for AJAX
            const requestData = {
                order_tracking_id: orderTrackingId,
                amount: amount,
                username: username,
                remarks: remarks
            };

            // Clear previous messages
            $('#refundErrorMessage').hide();
            $('#refundSuccessMessage').hide();

            // Disable the button and show loading
            var $refundButton = $('#processRefundButton');
            $refundButton.prop('disabled', true);
            $refundButton.html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...'
            );

            // AJAX request
            $.ajax({
                url: 'RefundRequestProcessor.php', // This script should handle the refund logic
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(requestData),
                success: function(response) {
                    if (response.success) {
                        // Access 'refund_response' and other relevant details from the response
                        const refundDetails = response.refund_response;

                        // Display basic success message
                        $('#refundSuccessMessage').html(
                            `<strong>${refundDetails.message}!</strong>`
                        ).show();
                        $('#refundErrorMessage').hide();

                    } else {
                        const errorMessage = response.error ||
                            'An error occurred while processing the refund.';
                        $('#refundErrorMessage').text(errorMessage).show();
                        $('#refundSuccessMessage').hide();
                    }

                    // Enable the button
                    $refundButton.prop('disabled', false);
                    $refundButton.html('Process Refund');
                },

                error: function(jqXHR) {
                    const errorResponse = jqXHR.responseJSON || {
                        error: {
                            message: 'There was an unexpected error. Please try again.'
                        }
                    };
                    const errorMessage = errorResponse.error.message || errorResponse.error;
                    $('#refundErrorMessage').text(errorMessage).show();
                    $('#refundSuccessMessage').hide();

                    // Enable the button
                    $refundButton.prop('disabled', false);
                    $refundButton.html('Process Refund');
                }
            });
        });
    });
    </script>
</body>

</html>