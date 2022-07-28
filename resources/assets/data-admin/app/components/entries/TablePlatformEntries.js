import React from 'react';
import { Table } from 'react-bootstrap';
import helpers from 'utils/helpers';

const TablePlatformEntries = ({ stats }) => {

    const total = stats.android + stats.ios + stats.web + stats.unknown;

    const percentages = {
        android: helpers.getPercentage(total, stats.android),
        ios: helpers.getPercentage(total, stats.ios),
        web: helpers.getPercentage(total, stats.web),
        unknown: helpers.getPercentage(total, stats.unknown)
    };

    return (
        <Table responsive condensed>
            <thead>
            <tr>
                <th><span className="platform-entries-color-legend android" />Android</th>
                <th><span className="platform-entries-color-legend ios" />iOS</th>
                <th><span className="platform-entries-color-legend web" />Web</th>
                <th><span className="platform-entries-color-legend unknown" />Unknown</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>{helpers.makeFriendlyNumber(stats.android)}</td>
                <td>{helpers.makeFriendlyNumber(stats.ios)}</td>
                <td>{helpers.makeFriendlyNumber(stats.web)}</td>
                <td>{helpers.makeFriendlyNumber(stats.unknown)}</td>
            </tr>
            <tr>
                <td>{helpers.makeFriendlyNumber(percentages.android)}</td>
                <td>{helpers.makeFriendlyNumber(percentages.ios)}</td>
                <td>{helpers.makeFriendlyNumber(percentages.web)}</td>
                <td>{helpers.makeFriendlyNumber(percentages.unknown)}</td>
            </tr>
            </tbody>
        </Table>
    );
};

export default TablePlatformEntries;
