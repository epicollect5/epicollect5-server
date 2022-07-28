import React from 'react';
import { Table } from 'react-bootstrap';
import helpers from 'utils/helpers';

const TableUsers = ({ stats }) => {

    return (
        <Table responsive condensed>
            <thead>
            <tr>
                <th>Overall</th>
                 <th>Yesterday</th>
                  <th>7-Days</th>
                  <th>Month</th>
                  <th>Year</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>{helpers.makeFriendlyNumber(stats.overall)}</td>
                <td>{helpers.makeFriendlyNumber(stats.today)}</td>
                <td>{helpers.makeFriendlyNumber(stats.week)}</td>
                 <td>{helpers.makeFriendlyNumber(stats.month)}</td>
                 <td>{helpers.makeFriendlyNumber(stats.year)}</td>
            </tr>
            </tbody>
        </Table>
    );
};

export default TableUsers;
