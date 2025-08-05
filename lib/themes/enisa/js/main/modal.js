/**
 * Function sets bootstrap modal.
 */
function setModal(obj)
{
    jQuery(function($) {
        var modal = (obj.modal) ? obj.modal : 'default';
        var btn = (obj.btn) ? obj.btn : 'Save changes';

        if (modal == 'default') {
            var header = { 'border-color': '#dee2e6', 'background-color': 'inherit', 'color': 'inherit' };
        }
        else if (modal == 'warning') {
            var header = {'border-color': '#ffecb5', 'background-color': '#fff3cd', 'color': '#664d03'};
        }

        $('#pageModal .modal-dialog').removeClass('modal-lg modal-xl');
        if (obj.large) {
            $('#pageModal .modal-dialog').addClass('modal-lg');
        }
        else if (obj.xlarge) {
            $('#pageModal .modal-dialog').addClass('modal-xl');
        }
        $('#pageModal .modal-header').css(header);
        $('#pageModal .modal-title').html(obj.title);
        $('#pageModal .modal-body').html(obj.html);
        $('#pageModal .process-data').html(btn);
        if (obj.id) {
            $('#pageModal #item-id').val(obj.id);
        }
        if (obj.data) {
            $('#pageModal #item-data').val(obj.data);
        }
        if (obj.action) {
            $('#pageModal #item-action').val(obj.action);
        }
        if (obj.type) {
            $('#pageModal #item-type').val(obj.type);
        }
        if (obj.route) {
            $('#pageModal #item-route').val(obj.route);
        }
    });
}