import React from 'react';
import { Table } from 'react-bootstrap';
import helpers from 'utils/helpers';

const TableProjectsActiveInactive = ({ stats }) => {

    const projectsTotal =  stats.projectsTotal.overall;
    const thresholdData = stats.projectsByThreshold.byThreshold;
    const inactiveTotal = thresholdData.below10;
    const activeTotal = projectsTotal - inactiveTotal;
    const activeTitle =  'Standard (>10 entries)';
    const inactiveTitle = 'Low (<10 entries)';

    return (
        <Table responsive condensed>
            <thead>
            <tr>
                <th>Projects Total</th>
                <th><span className="entries-color-legend public" />{activeTitle}</th>
                <th><span className="entries-color-legend private" />{inactiveTitle}</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>{helpers.makeFriendlyNumber(projectsTotal)}</td>
                <td>{helpers.makeFriendlyNumber(activeTotal)}</td>
                <td>{helpers.makeFriendlyNumber(inactiveTotal)}</td>
            </tr>
            </tbody>
        </Table>
    );
};

export default TableProjectsActiveInactive;
