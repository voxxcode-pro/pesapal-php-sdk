<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Check Transaction Status</title>
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

    <!-- Transaction Status Form -->
    <div class="container content-wrapper" id="statusCheckContainer">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="mt-5 text-center">Check Transaction Status</h2>
                <p class="text-muted text-center">Enter the Order Tracking ID to check the transaction status.</p>

                <!-- Success Message -->
                <div id="statusSuccessMessage" class="alert alert-success mt-4" style="display: none;">
                    Transaction status retrieved successfully!
                </div>

                <!-- Error Message -->
                <div id="statusErrorMessage" class="alert alert-danger mt-4" style="display: none;">
                    There was an error retrieving the transaction status. Please try again.
                </div>

                <!-- Status Check Form -->
                <form id="statusCheckForm">
                    <div class="form-group">
                        <label for="orderTrackingIdField">Order Tracking ID</label>
                        <input type="text" class="form-control" id="orderTrackingIdField" name="order_tracking_id"
                            placeholder="Enter Order Tracking ID" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block mb-4" id="checkStatusButton">Check
                        Status</button>
                </form>
                <!-- "Powered by Pesapal" Section -->
                <div class="powered-by-container">
                    <span class="powered-by-text">Powered by</span>
                    <img src="images/pesapal.png" alt="Pesapal Logo" class="pesapal-logo">
                </div>
            </div>
        </div>
    </div>

    <!-- Include jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <script>
    $(document).ready(function() {
        // Show content after page load
        $('#statusCheckContainer').addClass('content-visible');

        // Handle form submission
        $('#statusCheckForm').on('submit', function(e) {
            e.preventDefault();

            const orderTrackingId = $('#orderTrackingIdField').val();

            // Validate input
            if (!orderTrackingId) {
                $('#statusErrorMessage').text('Please enter a valid Order Tracking ID.').show();
                $('#statusSuccessMessage').hide();
                return;
            }

            // Prepare data for AJAX
            const requestData = {
                order_tracking_id: orderTrackingId
            };

            // Clear previous messages
            $('#statusErrorMessage').hide();
            $('#statusSuccessMessage').hide();

            // Disable the button and show loading
            var $checkButton = $('#checkStatusButton');
            $checkButton.prop('disabled', true);
            $checkButton.html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Checking...'
            );

            // AJAX request
            $.ajax({
                url: 'check_transaction_status.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(requestData),
                success: function(response) {
                    if (response.success) {
                        // Access 'transaction_status'
                        const transaction = response.transaction_status;

                        // Display status description
                        $('#statusSuccessMessage').html(
                            '<strong>Transaction Status:</strong> ' + (transaction
                                .payment_status_description || 'N/A')
                        ).show();
                        $('#statusErrorMessage').hide();

                        // Display additional transaction details
                        let detailsHtml = '<ul class="list-group mt-3">';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Status:</strong> ' + (
                                transaction.status_message || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Payment Method:</strong> ' +
                            (transaction.payment_method || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Amount:</strong> ' + (
                                transaction.amount || 'N/A') + ' ' + (transaction
                                .currency || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Created Date:</strong> ' +
                            (transaction.created_date || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Confirmation Code:</strong> ' +
                            (transaction.confirmation_code || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Order Tracking ID:</strong> ' +
                            (transaction.order_tracking_id || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Merchant Reference:</strong> ' +
                            (transaction.merchant_reference || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Description:</strong> ' + (
                                transaction.description || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Message:</strong> ' + (
                                transaction.message || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Payment Account:</strong> ' +
                            (transaction.payment_account || 'N/A') + '</li>';
                        detailsHtml +=
                            '<li class="list-group-item"><strong>Callback URL:</strong> ' +
                            '<a href="' + (transaction.call_back_url || '#') +
                            '" target="_blank" title="' + (transaction.call_back_url ||
                                'N/A') + '">' +
                            'View Callback' +
                            '</a></li>';

                        detailsHtml += '</ul>';

                        $('#statusSuccessMessage').append(detailsHtml);
                    } else {
                        const errorMessage = response.error ||
                            'An error occurred while checking the transaction status.';
                        $('#statusErrorMessage').text(errorMessage).show();
                        $('#statusSuccessMessage').hide();
                    }

                    // Enable the button
                    $checkButton.prop('disabled', false);
                    $checkButton.html('Check Status');
                },
                error: function(jqXHR) {
                    const errorResponse = jqXHR.responseJSON || {
                        error: {
                            message: 'There was an unexpected error. Please try again.'
                        }
                    };
                    const errorMessage = errorResponse.error.message || errorResponse.error;
                    $('#statusErrorMessage').text(errorMessage).show();
                    $('#statusSuccessMessage').hide();

                    // Enable the button
                    $checkButton.prop('disabled', false);
                    $checkButton.html('Check Status');
                }
            });
        });
    });
    </script>
</body>

</html>