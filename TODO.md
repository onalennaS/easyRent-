# TODO: Update Maintenance Requests to Show Landlord Full Name and Status

## Tasks
- [x] Update the SQL query in `dashboard/maintenance_requests.php` to use COALESCE for landlord_name
- [ ] Update the SQL query in `dashboard/get_request_details.php` to use COALESCE for landlord_name
- [x] Verify that the status is set to 'open' on submission (already implemented)
- [ ] Test the changes to ensure landlord name displays correctly and status is 'open'
