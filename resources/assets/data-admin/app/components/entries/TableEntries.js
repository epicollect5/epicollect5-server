import React from 'react';
import { Table } from 'react-bootstrap';
import helpers from 'utils/helpers';

const TableEntries = ({ stats }) => {
    return (
        <Table responsive condensed>
            <thead>
            <tr>
                <th>Overall</th>
                <th><span className="entries-color-legend public" />Public</th>
                <th><span className="entries-color-legend private" />Private</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>{helpers.makeFriendlyNumber(stats.overall)}</td>
                <td>{helpers.makeFriendlyNumber(stats.public)}</td>
                <td>{helpers.makeFriendlyNumber(stats.private)}</td>
            </tr>
            </tbody>
        </Table>
    );
};

export default TableEntries;
