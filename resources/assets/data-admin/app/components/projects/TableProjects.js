import React from 'react';
import { Table } from 'react-bootstrap';
import helpers from 'utils/helpers';

const TableProjects = ({ stats }) => {
    return (
        <Table responsive condensed>
            <thead>
            <tr>
                <th>Overall</th>
                <th colSpan="2" className="text-center"><span className="projects-color-legend public" />Public</th>
                <th colSpan="2" className="text-center"><span className="projects-color-legend private" />Private</th>
            </tr>
            <tr>
                <th />
                <th className="text-center"><span className="projects-color-legend listed" />Listed</th>
                <th className="text-center"><span className="projects-color-legend hidden" />Hidden</th>
                <th className="text-center"><span className="projects-color-legend listed" />Listed</th>
                <th className="text-center"><span className="projects-color-legend hidden" />Hidden</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td/>
                 <td className="text-center">{helpers.makeFriendlyNumber(stats.public.listed)}</td>
                 <td className="text-center">{helpers.makeFriendlyNumber(stats.public.hidden)}</td>
                 <td className="text-center">{helpers.makeFriendlyNumber(stats.private.listed)}</td>
                 <td className="text-center">{helpers.makeFriendlyNumber(stats.private.hidden)}</td>
            </tr>
            <tr>
                <td>{helpers.makeFriendlyNumber(stats.overall)}</td>
                 <td colSpan="2" className="text-center">{helpers.makeFriendlyNumber(stats.public.listed + stats.public.hidden)}</td>
                 <td colSpan="2" className="text-center">{helpers.makeFriendlyNumber(stats.private.listed + stats.private.hidden)}</td>

            </tr>
            </tbody>
        </Table>
    );
};

export default TableProjects;
