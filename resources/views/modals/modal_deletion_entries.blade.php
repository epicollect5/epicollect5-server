<div class="modal fade" id="modal-deletion" tabindex="-1" role="dialog" aria-labelledby="ec5ModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title text-center">Deleting, please wait...</h4>
            </div>
            <div class="modal-body">
                <div class="progress">
                    <div class="progress-bar progress-bar__modal-deletion__entries progress-bar-striped active"
                         role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="50"
                         style="width: 50%">
                    </div>
                    <div class="progress-bar progress-bar__modal-deletion__media progress-bar-striped active"
                         role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="50"
                         style="width: 50%">
                    </div>
                    <div class="clearfix"></div>
                </div>
                <table class="table">
                    <thead>
                    <tr>
                        <th>Type</th>
                        <th>Progress</th>
                        <th>Deleted</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="progress-media">
                        <td>
                            <span class="color-indicator color-media"></span>
                            Media
                        </td>
                        <td><strong><span class="counter-percentage">0%</span></strong></td>
                        <td><span class="counter-deleted">0</span></td>
                        <td>
                            <span class="spinner text-center"></span>
                            <span class="counter-total hidden">0</span>
                        </td>
                    </tr>
                    <tr class="progress-entries">
                        <td>
                            <span class="color-indicator color-entries"></span>
                            Entries
                        </td>
                        <td><strong><span class="counter-percentage">0%</span></strong></td>
                        <td><span class="counter-deleted">0</span></td>
                        <td><span class="counter-total">0</span></td>
                    </tr>
                    </tbody>
                </table>


            </div>
            <div class="modal-footer">
                <p class="warning-well">Please do not close this browser tab.</p>
            </div>
        </div>
    </div>
</div>
